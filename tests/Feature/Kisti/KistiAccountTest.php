<?php

namespace Tests\Feature\Kisti;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\Installment;
use App\Models\Member;
use App\Models\User;
use App\Services\Kisti\KistiAccountService;
use App\Services\SettingService;
use App\Services\Somiti\SomitiAccountService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KistiAccountTest extends TestCase
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

    private function open(array $overrides = []): Account
    {
        return app(KistiAccountService::class)->open($this->member, array_merge([
            'frequency' => 'daily',
            'loan_amount' => '20000',
            'service_charge' => '4000',
            'installment_amount' => '200',
            'start_date' => Carbon::parse('2026-11-21'),
            'first_installment_date' => Carbon::parse('2026-11-22'),
        ], $overrides), $this->admin);
    }

    private function dates(Account $account): array
    {
        return $account->installments()->orderBy('sequence_no')->pluck('due_date')->map->toDateString()->all();
    }

    public function test_daily_example_from_brief(): void
    {
        $account = $this->open();
        $k = $account->kistiDetail;

        $this->assertSame('KS-00001', $account->account_no);
        $this->assertSame('24000.00', (string) $k->total_payable);
        $this->assertSame(120, $k->total_installments);
        $this->assertSame(120, $account->installments()->count());
        $this->assertSame('24000.00', number_format((float) $account->installments()->sum('amount_due'), 2, '.', ''));
        $this->assertSame('2026-11-22', $this->dates($account)[0]);
    }

    public function test_weekly_example_from_brief(): void
    {
        $account = $this->open(['frequency' => 'weekly', 'installment_amount' => '1000', 'first_installment_date' => Carbon::parse('2026-11-28')]);

        $this->assertSame(24, $account->kistiDetail->total_installments);
        $dates = $this->dates($account);
        $this->assertSame(['2026-11-28', '2026-12-05', '2026-12-12'], array_slice($dates, 0, 3));
    }

    public function test_last_installment_carries_the_remainder(): void
    {
        $account = $this->open(['service_charge' => '4100']); // 24,100 / 200

        $this->assertSame(121, $account->kistiDetail->total_installments);
        $this->assertSame('100.00', (string) $account->kistiDetail->last_installment_amount);
        $this->assertSame('100.00', (string) $account->installments()->where('sequence_no', 121)->value('amount_due'));
        $this->assertSame('200.00', (string) $account->installments()->where('sequence_no', 120)->value('amount_due'));
    }

    public function test_daily_skips_off_days_and_weekly_moves_to_next_working_day(): void
    {
        app(SettingService::class)->set('weekly_off_days', [5]); // Friday
        Holiday::query()->create(['date' => '2026-12-05', 'title' => 'ছুটি']);

        $daily = $this->open(['first_installment_date' => Carbon::parse('2026-11-26')]); // Thu
        $this->assertSame(['2026-11-26', '2026-11-28', '2026-11-29'], array_slice($this->dates($daily), 0, 3)); // Fri 27 skipped

        $weekly = $this->open(['frequency' => 'weekly', 'installment_amount' => '1000', 'first_installment_date' => Carbon::parse('2026-11-28')]);
        $this->assertSame(['2026-11-28', '2026-12-06', '2026-12-12'], array_slice($this->dates($weekly), 0, 3)); // Sat 5 Dec holiday → Sun 6
    }

    public function test_new_holiday_moves_only_unpaid_future_installments(): void
    {
        $account = $this->open(['first_installment_date' => Carbon::parse('2026-11-22')]);
        // 23 Nov already paid in advance (Phase 5 will do this for real).
        Installment::query()->where('account_id', $account->id)->whereDate('due_date', '2026-11-23')
            ->update(['amount_paid' => '200.00', 'status' => 'paid']);

        $this->actingAs($this->admin)->post(route('admin.holidays.store'), ['date' => '2026-11-24', 'title' => 'হঠাৎ ছুটি']);

        $dates = $this->dates($account);
        $this->assertSame(['2026-11-22', '2026-11-23', '2026-11-25', '2026-11-26'], array_slice($dates, 0, 4));
        $this->assertNotContains('2026-11-24', $dates);
        $this->assertCount(120, array_unique($dates));

        // Removing the holiday pulls the schedule back.
        $holiday = Holiday::query()->whereDate('date', '2026-11-24')->firstOrFail();
        $this->actingAs($this->admin)->delete(route('admin.holidays.destroy', $holiday));
        $this->assertSame(['2026-11-22', '2026-11-23', '2026-11-24', '2026-11-25'], array_slice($this->dates($account), 0, 4));
    }

    public function test_changing_weekly_off_days_reflows_future_daily_installments(): void
    {
        $account = $this->open(['first_installment_date' => Carbon::parse('2026-11-22')]); // Sun
        $this->actingAs($this->admin)->put(route('admin.settings.update'), [
            'org_name' => 'সমিতি', 'share_value' => '20', 'weekly_off_days' => ['5'],
        ]);

        $fridays = array_filter($this->dates($account), fn ($d) => Carbon::parse($d)->isFriday());
        $this->assertSame([], array_values($fridays));
    }

    public function test_collector_creates_loan_for_own_member_with_bengali_amounts(): void
    {
        $this->actingAs($this->rahim)->post(route('collector.kisti.store'), [
            'member_id' => $this->member->id, 'frequency' => 'daily',
            'loan_amount' => '২০,০০০', 'service_charge' => '৪০০০', 'installment_amount' => '২০০',
            'start_date' => '2026-11-20', 'first_installment_date' => '2026-11-21',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(120, Account::query()->kisti()->firstOrFail()->kistiDetail->total_installments);
    }

    public function test_validation_rules(): void
    {
        $karim = User::factory()->create();
        $karimsMember = Member::factory()->create(['collector_id' => $karim->id]);
        $base = [
            'member_id' => $this->member->id, 'frequency' => 'daily', 'loan_amount' => '1000', 'service_charge' => '0',
            'installment_amount' => '100', 'start_date' => '2026-11-20', 'first_installment_date' => '2026-11-21',
        ];

        $this->actingAs($this->rahim)->post(route('collector.kisti.store'), ['member_id' => $karimsMember->id] + $base)
            ->assertSessionHasErrors('member_id');
        $this->actingAs($this->rahim)->post(route('collector.kisti.store'), ['first_installment_date' => '2026-11-19'] + $base)
            ->assertSessionHasErrors('first_installment_date');
        $this->actingAs($this->rahim)->post(route('collector.kisti.store'), ['installment_amount' => '5000'] + $base)
            ->assertSessionHasErrors('installment_amount');
        $this->actingAs($this->rahim)->post(route('collector.kisti.store'), ['installment_amount' => '0.50'] + $base)
            ->assertSessionHasErrors('installment_amount'); // 2000 installments > max
        $this->actingAs($this->rahim)->post(route('collector.kisti.store'), ['loan_amount' => '-5'] + $base)
            ->assertSessionHasErrors('loan_amount');
    }

    public function test_admin_cancels_unpaid_loan_but_not_one_with_money(): void
    {
        $unpaid = $this->open();
        $paid = $this->open();
        Installment::query()->where('account_id', $paid->id)->where('sequence_no', 1)->update(['amount_paid' => '50.00', 'status' => 'partial']);

        $this->actingAs($this->rahim)->post(route('admin.kisti.cancel', $unpaid), ['reason' => 'x'])->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.kisti.cancel', $unpaid), ['reason' => 'ভুল সদস্য'])->assertSessionHas('success');
        $this->assertSame(AccountStatus::Cancelled, $unpaid->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'kisti.cancelled')->exists());

        $this->actingAs($this->admin)->post(route('admin.kisti.cancel', $paid), ['reason' => 'x'])->assertSessionHasErrorsIn('cancel', 'reason');
        $this->assertSame(AccountStatus::Active, $paid->fresh()->status);

        $this->expectException(DomainException::class);
        app(KistiAccountService::class)->cancel($paid, 'x', $this->admin);
    }

    public function test_pages_render_and_collectors_are_scoped(): void
    {
        $account = $this->open();
        $karim = User::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.kisti.index'))->assertOk()->assertSee('KS-00001')->assertSee('৳২০,০০০');
        $this->actingAs($this->rahim)->get(route('collector.kisti.show', $account))->assertOk()->assertSee('কিস্তির তালিকা')->assertSee('১২০টি');
        $this->actingAs($karim)->get(route('collector.kisti.show', $account))->assertForbidden();
        $this->actingAs($this->rahim)->get(route('collector.kisti.create'))->assertOk()->assertSee('মোট পরিশোধযোগ্য');
    }

    public function test_register_shows_kisti_column_for_members_with_a_loan(): void
    {
        app(SomitiAccountService::class)->open($this->member, 5, Carbon::parse('2026-11-16'), $this->admin);
        $this->open();

        $this->actingAs($this->admin)->get(route('admin.somiti.register', ['month' => '2026-11']))
            ->assertOk()->assertSee('৳২০০')->assertSee('দৈনিক');
    }
}
