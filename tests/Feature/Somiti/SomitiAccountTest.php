<?php

namespace Tests\Feature\Somiti;

use App\Enums\InstallmentStatus;
use App\Models\Account;
use App\Models\Holiday;
use App\Models\Installment;
use App\Models\Member;
use App\Models\User;
use App\Services\SettingService;
use App\Services\Somiti\SomitiAccountService;
use App\Services\Somiti\SomitiScheduleService;
use App\Services\Somiti\SomitiShareChangeService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SomitiAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rahim;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00'); // Friday
        $this->admin = User::factory()->admin()->create();
        $this->rahim = User::factory()->create();
        $this->member = Member::factory()->create(['collector_id' => $this->rahim->id, 'joined_at' => '2026-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function open(int $shares = 5, string $start = '2026-11-16'): Account
    {
        return app(SomitiAccountService::class)->open($this->member, $shares, Carbon::parse($start), $this->admin);
    }

    public function test_five_shares_means_100_taka_daily_and_rows_up_to_today(): void
    {
        $account = $this->open(5, '2026-11-16'); // Mon 16 → Fri 20 = 5 days

        $this->assertSame('SM-00001', $account->account_no);
        $this->assertSame('100.00', (string) $account->somitiDetail->daily_deposit_amount);
        $this->assertSame('20.00', (string) $account->somitiDetail->share_value);
        $this->assertSame(5, $account->installments()->count());
        $this->assertSame('2026-11-20', $account->installments()->max('due_date'));
        $this->assertSame($this->rahim->id, $account->assigned_collector_id);
    }

    public function test_off_days_and_holidays_are_skipped(): void
    {
        app(SettingService::class)->set('weekly_off_days', [5]); // Friday
        Holiday::query()->create(['date' => '2026-11-18', 'title' => 'ছুটি']);

        $account = $this->open(1, '2026-11-14'); // Sat 14 … Fri 20

        $dates = $account->installments()->orderBy('due_date')->pluck('due_date')->map->toDateString()->all();
        $this->assertSame(['2026-11-14', '2026-11-15', '2026-11-16', '2026-11-17', '2026-11-19'], $dates);
    }

    public function test_changing_share_value_setting_does_not_touch_existing_accounts(): void
    {
        $old = $this->open(5);
        app(SettingService::class)->set('share_value', '25');
        $new = $this->open(5);

        $this->assertSame('100.00', (string) $old->fresh()->somitiDetail->daily_deposit_amount);
        $this->assertSame('125.00', (string) $new->somitiDetail->daily_deposit_amount);
        $this->assertSame('100.00', (string) $old->installments()->first()->amount_due);
    }

    public function test_roll_is_idempotent_and_continues_next_day(): void
    {
        $account = $this->open(5, '2026-11-16');
        $schedule = app(SomitiScheduleService::class);

        $this->assertSame(0, $schedule->ensureThrough($account, today()));
        Carbon::setTestNow('2026-11-22 09:00:00');
        $this->assertSame(2, $schedule->rollAll(today(), force: true)); // Sat 21, Sun 22
        $this->assertSame(7, $account->installments()->count());
    }

    public function test_share_increase_keeps_earlier_days_and_shows_difference_on_prepaid_days(): void
    {
        $account = $this->open(5, '2026-11-16');
        $schedule = app(SomitiScheduleService::class);
        $schedule->ensureThrough($account, Carbon::parse('2026-11-30'));
        // Simulate an advance payment at the old rate for 25–30 Nov (Phase 5 will do this for real).
        Installment::query()->where('account_id', $account->id)->whereBetween('due_date', ['2026-11-25', '2026-11-30'])
            ->update(['amount_paid' => '100.00', 'status' => 'paid']);

        app(SomitiShareChangeService::class)->change($account, 6, Carbon::parse('2026-11-25'), $this->admin, 'শেয়ার বাড়ানো');

        $before = Installment::query()->where('account_id', $account->id)->whereDate('due_date', '2026-11-24')->first();
        $after = Installment::query()->where('account_id', $account->id)->whereDate('due_date', '2026-11-25')->first();
        $this->assertSame('100.00', (string) $before->amount_due);
        $this->assertSame('120.00', (string) $after->amount_due);
        $this->assertSame(InstallmentStatus::Partial, $after->status);
        $this->assertSame(6, $account->fresh()->somitiDetail->number_of_shares);

        // New rows generated later use the new amount.
        $schedule->ensureThrough($account, Carbon::parse('2026-12-02'));
        $this->assertSame('120.00', (string) Installment::query()->where('account_id', $account->id)->whereDate('due_date', '2026-12-02')->value('amount_due'));
    }

    public function test_share_decrease_refused_over_prepaid_days(): void
    {
        $account = $this->open(5, '2026-11-16');
        app(SomitiScheduleService::class)->ensureThrough($account, Carbon::parse('2026-11-28'));
        Installment::query()->where('account_id', $account->id)->whereDate('due_date', '2026-11-27')
            ->update(['amount_paid' => '100.00', 'status' => 'paid']);

        $this->expectException(DomainException::class);
        app(SomitiShareChangeService::class)->change($account, 3, Carbon::parse('2026-11-25'), $this->admin);
    }

    public function test_share_change_cannot_be_backdated(): void
    {
        $account = $this->open(5, '2026-11-16');

        $this->actingAs($this->admin)
            ->post(route('admin.somiti.shares', $account), ['new_shares' => 6, 'effective_date' => '2026-11-19'])
            ->assertSessionHasErrorsIn('shares', 'effective_date');
    }

    public function test_holiday_added_removes_unpaid_day_but_keeps_paid_day(): void
    {
        $a = $this->open(5, '2026-11-16');
        $b = app(SomitiAccountService::class)->open(Member::factory()->create(['collector_id' => $this->rahim->id, 'joined_at' => '2026-01-01']), 2, Carbon::parse('2026-11-16'), $this->admin);
        Installment::query()->where('account_id', $b->id)->whereDate('due_date', '2026-11-19')->update(['amount_paid' => '40.00', 'status' => 'paid']);

        $this->actingAs($this->admin)->post(route('admin.holidays.store'), ['date' => '2026-11-19', 'title' => 'হঠাৎ ছুটি']);

        $this->assertFalse($a->installments()->whereDate('due_date', '2026-11-19')->exists());
        $this->assertTrue($b->installments()->whereDate('due_date', '2026-11-19')->exists());
    }

    public function test_collector_opens_account_only_for_own_active_member(): void
    {
        $karim = User::factory()->create();
        $karimsMember = Member::factory()->create(['collector_id' => $karim->id]);
        $inactive = Member::factory()->create(['collector_id' => $this->rahim->id, 'status' => 'inactive']);

        $this->actingAs($this->rahim)->post(route('collector.somiti.store'), [
            'member_id' => $this->member->id, 'number_of_shares' => '৩', 'start_date' => '2026-11-20',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('60.00', (string) Account::query()->firstOrFail()->somitiDetail->daily_deposit_amount);

        $this->actingAs($this->rahim)->post(route('collector.somiti.store'), [
            'member_id' => $karimsMember->id, 'number_of_shares' => 1, 'start_date' => '2026-11-20',
        ])->assertSessionHasErrors('member_id');
        $this->actingAs($this->rahim)->post(route('collector.somiti.store'), [
            'member_id' => $inactive->id, 'number_of_shares' => 1, 'start_date' => '2026-11-20',
        ])->assertSessionHasErrors('member_id');
    }

    public function test_collector_cannot_view_other_collectors_account_or_change_shares(): void
    {
        $karim = User::factory()->create();
        $account = $this->open();

        $this->actingAs($karim)->get(route('collector.somiti.show', $account))->assertForbidden();
        $this->actingAs($this->rahim)->get(route('collector.somiti.show', $account))->assertOk()->assertSee('জমার ক্যালেন্ডার');
        $this->actingAs($this->rahim)->post(route('admin.somiti.shares', $account), ['new_shares' => 9, 'effective_date' => '2026-11-21'])->assertForbidden();
    }

    public function test_reassigning_member_moves_accounts_to_new_collector(): void
    {
        $karim = User::factory()->create();
        $account = $this->open();

        app(\App\Services\Members\MemberAssignmentService::class)->assign($this->member, $karim, $this->admin);

        $this->assertSame($karim->id, $account->fresh()->assigned_collector_id);
    }

    public function test_register_and_list_pages_render_in_bengali(): void
    {
        $this->open(5, '2026-11-16');

        $this->actingAs($this->admin)->get(route('admin.somiti.register', ['month' => '2026-11']))
            ->assertOk()->assertSee('সমিতি রেজিস্টার')->assertSee('নভেম্বর ২০২৬')->assertSee($this->member->name)->assertSee('৳৫০০');
        $this->actingAs($this->admin)->get(route('admin.somiti.index'))->assertOk()->assertSee('SM-00001');
        $this->actingAs($this->rahim)->get(route('collector.somiti.register'))->assertOk()->assertSee($this->member->name);
    }

    public function test_existing_member_starts_today_with_opening_balance(): void
    {
        $this->actingAs($this->rahim)->post(route('collector.somiti.store'), [
            'member_id' => $this->member->id,
            'number_of_shares' => 5,
            'start_date' => '2026-11-20',
            'opening_balance' => '12500',
            'opening_balance_note' => 'পুরনো খাতা',
        ])->assertRedirect()->assertSessionHas('success');

        $account = Account::query()->firstOrFail();
        $this->assertSame('12500.00', (string) $account->somitiDetail->opening_balance);
        $this->assertSame($this->rahim->id, $account->somitiDetail->opening_balance_by);
        $this->assertSame(1, $account->installments()->count()); // only today

        $summary = app(\App\Services\Somiti\SomitiStatementService::class)->summary($account);
        $this->assertSame('12500.00', $summary['total_saved']);
        $this->assertSame('0.00', $summary['collected']);
        $this->assertSame('100.00', $summary['due']);

        $this->actingAs($this->rahim)->get(route('collector.somiti.show', $account))->assertSee('৳১২,৫০০');
    }

    public function test_opening_balance_accepts_bengali_digits_and_rejects_negative(): void
    {
        $this->actingAs($this->rahim)->post(route('collector.somiti.store'), [
            'member_id' => $this->member->id, 'number_of_shares' => 1, 'start_date' => '2026-11-20',
            'opening_balance' => '৫০০০',
        ])->assertSessionHas('success');
        $this->assertSame('5000.00', (string) Account::query()->firstOrFail()->somitiDetail->opening_balance);

        $this->actingAs($this->rahim)->post(route('collector.somiti.store'), [
            'member_id' => $this->member->id, 'number_of_shares' => 1, 'start_date' => '2026-11-20',
            'opening_balance' => '-10',
        ])->assertSessionHasErrors('opening_balance');
    }

    public function test_past_start_date_still_allowed_for_day_by_day_history(): void
    {
        $account = $this->open(1, '2026-11-02');

        $this->assertSame(19, $account->installments()->count()); // Mon 2 – Fri 20 Nov, no off-days configured
        $this->assertSame('0.00', (string) $account->somitiDetail->opening_balance);
    }

    public function test_only_admin_can_correct_opening_balance_and_it_is_audited(): void
    {
        $account = $this->open();

        $this->actingAs($this->rahim)->put(route('admin.somiti.opening', $account), ['opening_balance' => 999, 'reason' => 'x'])
            ->assertForbidden();

        $this->actingAs($this->admin)->put(route('admin.somiti.opening', $account), ['opening_balance' => '8000'])
            ->assertSessionHasErrorsIn('opening', 'reason');

        $this->actingAs($this->admin)->put(route('admin.somiti.opening', $account), ['opening_balance' => '8000', 'reason' => 'খাতা মিলিয়ে সংশোধন'])
            ->assertSessionHas('success');

        $this->assertSame('8000.00', (string) $account->fresh()->somitiDetail->opening_balance);
        $log = \App\Models\AuditLog::query()->where('action', 'somiti.opening_balance_corrected')->firstOrFail();
        $this->assertSame('0.00', $log->old_values['opening_balance']);
        $this->assertSame('8000.00', $log->new_values['opening_balance']);
    }

    public function test_opening_balance_with_bengali_comma_and_negative_bengali_amount(): void
    {
        $this->actingAs($this->rahim)->post(route('collector.somiti.store'), [
            'member_id' => $this->member->id, 'number_of_shares' => 1, 'start_date' => '2026-11-20',
            'opening_balance' => '১২,৫০০',
        ])->assertSessionHas('success');
        $this->assertSame('12500.00', (string) Account::query()->firstOrFail()->somitiDetail->opening_balance);

        $this->actingAs($this->rahim)->post(route('collector.somiti.store'), [
            'member_id' => $this->member->id, 'number_of_shares' => 1, 'start_date' => '2026-11-20',
            'opening_balance' => '-১০',
        ])->assertSessionHasErrors('opening_balance');
    }
}
