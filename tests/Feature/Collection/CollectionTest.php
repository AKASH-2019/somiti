<?php

namespace Tests\Feature\Collection;

use App\Enums\AccountStatus;
use App\Enums\AllocationTiming;
use App\Enums\EntryType;
use App\Enums\InstallmentStatus;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\CashLedgerEntry;
use App\Models\Installment;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Cash\CashLedgerService;
use App\Services\Collection\CollectionException;
use App\Services\Collection\CollectionService;
use App\Services\Collection\ReversalService;
use App\Services\Kisti\KistiAccountService;
use App\Services\Somiti\SomitiAccountService;
use App\Services\Somiti\SomitiShareChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rahim;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00'); // Friday; no off-days configured
        $this->admin = User::factory()->admin()->create();
        $this->rahim = User::factory()->create();
        $this->member = Member::factory()->create(['collector_id' => $this->rahim->id, 'joined_at' => '2026-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function somiti(int $shares = 5, string $start = '2026-11-16'): Account
    {
        return app(SomitiAccountService::class)->open($this->member, $shares, Carbon::parse($start), $this->admin);
    }

    private function kisti(): Account
    {
        return app(KistiAccountService::class)->open($this->member, [
            'frequency' => 'daily', 'loan_amount' => '1000', 'service_charge' => '200', 'installment_amount' => '200',
            'start_date' => Carbon::parse('2026-11-14'), 'first_installment_date' => Carbon::parse('2026-11-16'),
        ], $this->admin); // 6 × 200, 16–21 Nov
    }

    private function collect(Account $account, array $keys, string $amount, ?User $by = null, ?string $key = null): Transaction
    {
        return app(CollectionService::class)->collect($account, $by ?? $this->rahim, $keys, $amount, $key ?? (string) Str::uuid());
    }

    private function kistiIds(Account $account, array $sequences): array
    {
        return $account->installments()->whereIn('sequence_no', $sequences)->orderBy('sequence_no')->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function test_brief_example_500_taka_for_five_days_creates_one_transaction_and_five_allocations(): void
    {
        $account = $this->somiti(5, '2026-11-16');

        $tx = $this->collect($account, ['2026-11-19', '2026-11-20', '2026-11-21', '2026-11-22', '2026-11-23'], '500');

        $this->assertSame('500.00', (string) $tx->amount);
        $this->assertSame('RCP-2026-000001', $tx->receipt_no);
        $this->assertSame(5, $tx->allocations()->count());
        $this->assertSame(['100.00'], $tx->allocations->pluck('amount')->map(fn ($a) => (string) $a)->unique()->values()->all());
        $timing = $tx->allocations->mapWithKeys(fn ($a) => [$a->covered_date->toDateString() => $a->timing])->all();
        $this->assertSame(AllocationTiming::Previous, $timing['2026-11-19']);
        $this->assertSame(AllocationTiming::Current, $timing['2026-11-20']);
        $this->assertSame(AllocationTiming::Advance, $timing['2026-11-23']);
        $this->assertSame('2026-11-20', $tx->collected_at->toDateString()); // money received today, covers other days
        $this->assertSame(InstallmentStatus::Paid, $account->installments()->whereDate('due_date', '2026-11-23')->first()->status);
        $this->assertSame('500.00', app(CashLedgerService::class)->balance($this->rahim));
    }

    public function test_somiti_requires_exact_whole_days(): void
    {
        $account = $this->somiti();

        $this->expectException(CollectionException::class);
        $this->collect($account, ['2026-11-16', '2026-11-17'], '150');
    }

    public function test_same_day_cannot_be_paid_twice(): void
    {
        $account = $this->somiti();
        $this->collect($account, ['2026-11-16'], '100');

        $this->expectException(CollectionException::class);
        $this->collect($account, ['2026-11-16'], '100');
    }

    public function test_double_submit_with_same_key_saves_once(): void
    {
        $account = $this->somiti();
        $key = (string) Str::uuid();

        $first = $this->collect($account, ['2026-11-16'], '100', key: $key);
        $second = $this->collect($account, ['2026-11-16'], '100', key: $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Transaction::query()->count());
        $this->assertSame('100.00', app(CashLedgerService::class)->balance($this->rahim));
    }

    public function test_share_increase_difference_can_be_topped_up(): void
    {
        $account = $this->somiti(5);
        $this->collect($account, ['2026-11-25'], '100'); // advance at old rate
        app(SomitiShareChangeService::class)->change($account, 6, Carbon::parse('2026-11-25'), $this->admin);

        $this->collect($account, ['2026-11-25'], '20');

        $row = $account->installments()->whereDate('due_date', '2026-11-25')->first();
        $this->assertSame('120.00', (string) $row->amount_paid);
        $this->assertSame(InstallmentStatus::Paid, $row->status);
    }

    public function test_kisti_partial_then_completion_oldest_first(): void
    {
        $account = $this->kisti();

        $this->collect($account, $this->kistiIds($account, [1, 2, 3]), '500');
        $third = $account->installments()->where('sequence_no', 3)->first();
        $this->assertSame('100.00', (string) $third->amount_paid);
        $this->assertSame(InstallmentStatus::Partial, $third->status);

        $this->collect($account, $this->kistiIds($account, [3]), '100');
        $this->assertSame(InstallmentStatus::Paid, $third->fresh()->status);
    }

    public function test_kisti_rejects_overpayment_and_needless_selection(): void
    {
        $account = $this->kisti();

        try {
            $this->collect($account, $this->kistiIds($account, [1]), '300');
            $this->fail('Overpayment accepted');
        } catch (CollectionException) {
        }
        try {
            $this->collect($account, $this->kistiIds($account, [1, 2, 3]), '200');
            $this->fail('Installments with no money accepted');
        } catch (CollectionException) {
        }
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_fully_paid_loan_closes_and_reversal_reopens_it(): void
    {
        $account = $this->kisti();
        $tx = $this->collect($account, $this->kistiIds($account, [1, 2, 3, 4, 5, 6]), '1200');
        $this->assertSame(AccountStatus::Closed, $account->fresh()->status);

        app(ReversalService::class)->reverse($tx, 'ভুল এন্ট্রি', $this->admin);

        $this->assertSame(AccountStatus::Active, $account->fresh()->status);
        $this->assertSame(0, $account->installments()->where('amount_paid', '>', 0)->count());
    }

    public function test_reversal_restores_dues_and_debits_cash_and_cannot_repeat(): void
    {
        $account = $this->somiti();
        $tx = $this->collect($account, ['2026-11-16', '2026-11-17'], '200');

        app(ReversalService::class)->reverse($tx, 'ভুল সদস্য', $this->admin);

        $tx->refresh();
        $this->assertSame(TransactionStatus::Reversed, $tx->status);
        $this->assertSame(['reversed'], $tx->allocations->pluck('status')->unique()->values()->all());
        $this->assertSame(InstallmentStatus::Unpaid, $account->installments()->whereDate('due_date', '2026-11-16')->first()->status);
        $this->assertSame('0.00', app(CashLedgerService::class)->balance($this->rahim));
        $this->assertSame(2, CashLedgerEntry::query()->where('collector_id', $this->rahim->id)->count()); // credit + debit, nothing deleted

        $this->expectException(CollectionException::class);
        app(ReversalService::class)->reverse($tx, 'আবার', $this->admin);
    }

    public function test_reversal_permissions(): void
    {
        $account = $this->somiti();
        $tx = $this->collect($account, ['2026-11-16'], '100');

        $this->actingAs($this->rahim)->post(route('admin.transactions.reverse', $tx), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.transactions.reverse', $tx), [])->assertSessionHasErrorsIn('reverse', 'reason');
        $this->actingAs($this->admin)->post(route('admin.transactions.reverse', $tx), ['reason' => 'ভুল'])->assertSessionHas('success');
        $this->assertTrue($tx->fresh()->isReversed());
    }

    public function test_collector_cannot_collect_on_someone_elses_account_or_inactive_member(): void
    {
        $account = $this->somiti();
        $karim = User::factory()->create();

        $this->actingAs($karim)->get(route('collector.collections.create', $account))->assertForbidden();
        $this->actingAs($karim)->post(route('collector.collections.store', $account), [
            'keys' => ['2026-11-16'], 'amount' => '100', 'idempotency_key' => (string) Str::uuid(),
        ])->assertForbidden();

        $this->member->update(['status' => 'inactive']);
        $this->expectException(CollectionException::class);
        $this->collect($account, ['2026-11-17'], '100');
    }

    public function test_http_collection_redirects_to_receipt_in_bengali(): void
    {
        $account = $this->somiti();

        $response = $this->actingAs($this->rahim)->post(route('collector.collections.store', $account), [
            'keys' => ['2026-11-16', '2026-11-17', '2026-11-18'], 'amount' => '৩০০', 'idempotency_key' => (string) Str::uuid(),
            'entry_type' => 'historical', 'collected_at' => '2026-01-01', // ignored for collectors
        ]);
        $tx = Transaction::query()->firstOrFail();
        $response->assertRedirect(route('collector.transactions.show', $tx));

        $this->assertSame(EntryType::Cash, $tx->entry_type);
        $this->assertSame('2026-11-20', $tx->collected_at->toDateString());

        $this->actingAs($this->rahim)->get(route('collector.transactions.show', $tx))
            ->assertOk()->assertSee('টাকা জমার রসিদ')->assertSee('তিনশত টাকা মাত্র')->assertSee('১৬ – ১৮ নভেম্বর ২০২৬ (৩ দিন)')->assertSee('নগদ');
        $this->actingAs($this->rahim)->get(route('collector.transactions.show', [$tx, 'layout' => 'compact']))->assertOk();
    }

    public function test_wrong_amount_shows_error_and_creates_nothing(): void
    {
        $account = $this->somiti();

        $this->actingAs($this->rahim)->from(route('collector.collections.create', $account))
            ->post(route('collector.collections.store', $account), [
                'keys' => ['2026-11-16'], 'amount' => '90', 'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasErrors('amount');

        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_admin_historical_entry_updates_dues_but_not_cash(): void
    {
        $account = $this->somiti(5, '2026-11-02');

        $this->actingAs($this->admin)->post(route('admin.collections.store', $account), [
            'keys' => ['2026-11-02', '2026-11-03'], 'amount' => '200', 'idempotency_key' => (string) Str::uuid(),
            'entry_type' => 'historical', 'collected_at' => '2026-11-03', 'notes' => 'পুরনো খাতা পৃষ্ঠা ১২',
        ])->assertRedirect();

        $tx = Transaction::query()->firstOrFail();
        $this->assertSame(EntryType::Historical, $tx->entry_type);
        $this->assertSame('2026-11-03', $tx->collected_at->toDateString());
        $this->assertSame(0, CashLedgerEntry::query()->count());
        $this->assertSame(InstallmentStatus::Paid, $account->installments()->whereDate('due_date', '2026-11-02')->first()->status);
    }

    public function test_collection_pages_render(): void
    {
        $somiti = $this->somiti();
        $kisti = $this->kisti();

        $this->actingAs($this->rahim)->get(route('collector.collections.index'))->assertRedirect(route('collector.collections.somiti'));
        $this->actingAs($this->rahim)->get(route('collector.collections.somiti'))->assertOk()->assertSee('সমিতি সংগ্রহ')
            ->assertSee($somiti->account_no)->assertDontSee($kisti->account_no);
        $this->actingAs($this->rahim)->get(route('collector.collections.kisti'))->assertOk()->assertSee('কিস্তি সংগ্রহ')
            ->assertSee($kisti->account_no)->assertDontSee($somiti->account_no);
        $this->actingAs($this->rahim)->get(route('collector.collections.create', $somiti))->assertOk()->assertSee('জমা নিন ও রসিদ দিন');
        $this->actingAs($this->rahim)->get(route('collector.collections.create', $kisti))->assertOk()->assertSee('কোন কিস্তির জমা', false);
        $this->collect($somiti, ['2026-11-16'], '100');
        $this->actingAs($this->admin)->get(route('admin.transactions.index'))->assertOk()->assertSee('RCP-2026-000001');
        $this->actingAs($this->rahim)->get(route('collector.somiti.show', $somiti))->assertOk()->assertSee('সাম্প্রতিক লেনদেন');
    }
}
