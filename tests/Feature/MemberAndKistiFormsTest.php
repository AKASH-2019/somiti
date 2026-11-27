<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Member;
use App\Models\User;
use App\Services\Collection\CollectionOptionsService;
use App\Services\Collection\CollectionService;
use App\Services\Somiti\SomitiAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberAndKistiFormsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rahim;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->admin = User::factory()->admin()->create();
        $this->rahim = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(array $extra = []): array
    {
        return array_merge([
            'name' => 'আব্দুল করিম', 'business_name' => 'করিম স্টোর', 'mobile' => '01812345678', 'joined_at' => '2026-11-01',
        ], $extra);
    }

    public function test_new_member_and_somiti_account_in_one_save(): void
    {
        $this->actingAs($this->rahim)->post(route('collector.members.store'), $this->member([
            'open_somiti' => '1', 'number_of_shares' => '৫', 'somiti_start_date' => '2026-11-16',
            'opening_balance' => '৩,০০০', 'opening_balance_note' => 'পুরনো খাতা',
        ]))->assertRedirect()->assertSessionHas('success');

        $member = Member::query()->firstOrFail();
        $this->assertSame('S5-001', $member->member_code);
        $account = $member->accounts()->firstOrFail();
        $this->assertSame('100.00', (string) $account->somitiDetail->daily_deposit_amount);
        $this->assertSame('3000.00', (string) $account->somitiDetail->opening_balance);
        $this->assertSame(5, $account->installments()->count()); // 16–20 Nov are due already
    }

    public function test_member_without_somiti_when_box_unticked(): void
    {
        $this->actingAs($this->rahim)->post(route('collector.members.store'), $this->member(['number_of_shares' => '']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, Account::query()->count());
    }

    public function test_somiti_start_cannot_be_before_joining(): void
    {
        $this->actingAs($this->rahim)->post(route('collector.members.store'), $this->member([
            'open_somiti' => '1', 'number_of_shares' => '1', 'somiti_start_date' => '2026-10-01',
        ]))->assertSessionHasErrors('somiti_start_date');

        $this->assertSame(0, Member::query()->count()); // nothing half-saved
    }

    public function test_member_id_can_be_typed_and_next_one_follows(): void
    {
        $this->actingAs($this->rahim)->post(route('collector.members.store'), $this->member(['member_code' => 's5-০১০']));
        $this->assertSame('S5-010', Member::query()->firstOrFail()->member_code);

        $this->actingAs($this->rahim)->get(route('collector.members.create'))->assertOk()->assertSee('S5-011');

        $this->actingAs($this->rahim)->post(route('collector.members.store'), $this->member(['member_code' => 'S5-010', 'mobile' => '01712345678']))
            ->assertSessionHasErrors('member_code');
    }

    public function test_prefix_comes_from_settings(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['org_name' => 'সমিতি', 'share_value' => '20', 'member_id_prefix' => 'b2']);

        $this->actingAs($this->rahim)->post(route('collector.members.store'), $this->member());
        $this->assertSame('B2-001', Member::query()->firstOrFail()->member_code);
    }

    public function test_only_admin_can_change_member_id(): void
    {
        $this->actingAs($this->rahim)->post(route('collector.members.store'), $this->member());
        $member = Member::query()->firstOrFail();

        $this->actingAs($this->rahim)->put(route('collector.members.update', $member), $this->member(['member_code' => 'S5-099']));
        $this->assertSame('S5-001', $member->fresh()->member_code);

        $this->actingAs($this->admin)->put(route('admin.members.update', $member), $this->member(['member_code' => 'S5-004']))->assertSessionHasNoErrors();
        $this->assertSame('S5-004', $member->fresh()->member_code);
    }

    public function test_kisti_for_a_new_member_creates_both(): void
    {
        $this->actingAs($this->rahim)->post(route('collector.kisti.store'), $this->member([
            'member_mode' => 'new', 'frequency' => 'weekly',
            'loan_amount' => '20000', 'service_charge' => '4000', 'installment_amount' => '1000',
            'start_date' => '2026-11-20', 'first_installment_date' => '2026-11-27', 'notes' => 'ঋণের নোট',
        ]))->assertRedirect()->assertSessionHas('success');

        $member = Member::query()->firstOrFail();
        $this->assertSame($this->rahim->id, $member->collector_id);
        $this->assertNull($member->notes); // the note belongs to the loan
        $loan = $member->accounts()->firstOrFail();
        $this->assertSame(24, $loan->kistiDetail->total_installments);
        $this->assertSame('ঋণের নোট', $loan->notes);
    }

    public function test_kisti_for_existing_member_still_works(): void
    {
        $member = Member::factory()->create(['collector_id' => $this->rahim->id, 'joined_at' => '2026-01-01']);

        $this->actingAs($this->rahim)->post(route('collector.kisti.store'), [
            'member_mode' => 'existing', 'member_id' => $member->id, 'frequency' => 'daily',
            'loan_amount' => '1000', 'service_charge' => '200', 'installment_amount' => '200',
            'start_date' => '2026-11-20', 'first_installment_date' => '2026-11-21',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, Member::query()->count());
        $this->assertSame(6, $member->accounts()->firstOrFail()->kistiDetail->total_installments);
    }

    public function test_collection_screen_shows_previous_days_and_paid_ones(): void
    {
        $member = Member::factory()->create(['collector_id' => $this->rahim->id, 'joined_at' => '2026-01-01']);
        $account = app(SomitiAccountService::class)->open($member, 1, Carbon::parse('2026-11-16'), $this->admin);
        app(CollectionService::class)->collect($account, $this->rahim, ['2026-11-16'], '20', (string) Str::uuid());

        $items = collect(app(CollectionOptionsService::class)->forAccount($account))->keyBy('key');

        $this->assertTrue($items['2026-11-16']['paid']);           // paid, shown greyed
        $this->assertFalse($items['2026-11-17']['paid']);          // missed day, can be collected now
        $this->assertSame('previous', $items['2026-11-17']['kind']);
        $this->assertSame('current', $items['2026-11-20']['kind']);

        $this->actingAs($this->rahim)->get(route('collector.collections.create', $account))
            ->assertOk()->assertSee('পরিশোধিত দিনও দেখান');
    }
}
