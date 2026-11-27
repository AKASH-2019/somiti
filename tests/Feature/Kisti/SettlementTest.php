<?php

namespace Tests\Feature\Kisti;

use App\Enums\AccountStatus;
use App\Enums\InstallmentStatus;
use App\Models\Account;
use App\Models\KistiSettlement;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Cash\CashLedgerService;
use App\Services\Collection\CollectionException;
use App\Services\Collection\CollectionService;
use App\Services\Collection\ReversalService;
use App\Services\Kisti\KistiAccountService;
use App\Services\Kisti\KistiStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rahim;

    private Account $loan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->admin = User::factory()->admin()->create();
        $this->rahim = User::factory()->create();
        $member = Member::factory()->create(['collector_id' => $this->rahim->id, 'joined_at' => '2026-01-01']);
        $this->loan = app(KistiAccountService::class)->open($member, [
            'frequency' => 'daily', 'loan_amount' => '1000', 'service_charge' => '200', 'installment_amount' => '200',
            'start_date' => Carbon::parse('2026-11-14'), 'first_installment_date' => Carbon::parse('2026-11-16'),
        ], $this->admin); // 6 × 200 = 1200
        // First installment paid normally
        app(CollectionService::class)->collect($this->loan, $this->rahim,
            [(string) $this->loan->installments()->where('sequence_no', 1)->value('id')], '200', (string) Str::uuid());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function settle(string $amount = '500', ?User $receiver = null)
    {
        return $this->actingAs($this->admin)->post(route('admin.kisti.settle', $this->loan), [
            'amount' => $amount, 'receiver_id' => ($receiver ?? $this->rahim)->id, 'reason' => 'এককালীন পরিশোধ',
        ]);
    }

    public function test_settlement_takes_less_and_waives_the_rest(): void
    {
        $this->settle('500')->assertRedirect()->assertSessionHas('success');

        $loan = $this->loan->fresh();
        $this->assertSame(AccountStatus::Settled, $loan->status);
        $s = KistiSettlement::query()->firstOrFail();
        $this->assertSame('1000.00', (string) $s->outstanding_before);
        $this->assertSame('500.00', (string) $s->settled_amount);
        $this->assertSame('500.00', (string) $s->waived_amount);

        $summary = app(KistiStatementService::class)->summary($loan);
        $this->assertSame('0.00', $summary['remaining']);
        $this->assertSame('500.00', $summary['waived']);
        $this->assertSame('700.00', $summary['paid']);

        // 500 paid installments 2–3 (200+200) and 100 of #4; the rest is waived.
        $four = $loan->installments()->where('sequence_no', 4)->first();
        $this->assertSame('100.00', (string) $four->amount_paid);
        $this->assertSame('100.00', (string) $four->amount_waived);
        $this->assertSame(InstallmentStatus::Waived, $loan->installments()->where('sequence_no', 6)->first()->status);

        // Cash goes to the receiver: 200 (normal) + 500 (settlement)
        $this->assertSame('700.00', app(CashLedgerService::class)->balance($this->rahim));

        $this->actingAs($this->admin)->get(route('admin.kisti.show', $loan))->assertOk()->assertSee('সেটেলমেন্টে বন্ধ')->assertSee('মওকুফ');
        $this->actingAs($this->admin)->get(route('admin.transactions.show', $s->transaction_id))->assertOk()->assertSee('সেটেলমেন্ট');
    }

    public function test_reversing_the_settlement_restores_the_loan(): void
    {
        $this->settle('500');
        $tx = Transaction::query()->findOrFail(KistiSettlement::query()->firstOrFail()->transaction_id);

        app(ReversalService::class)->reverse($tx, 'ভুল', $this->admin);

        $loan = $this->loan->fresh();
        $this->assertSame(AccountStatus::Active, $loan->status);
        $this->assertSame('1000.00', app(KistiStatementService::class)->summary($loan)['remaining']);
        $this->assertSame(0, $loan->installments()->where('amount_waived', '>', 0)->count());
        $this->assertSame('reversed', KistiSettlement::query()->firstOrFail()->status);
        $this->assertSame('200.00', app(CashLedgerService::class)->balance($this->rahim));
    }

    public function test_older_payment_cannot_be_reversed_while_settled(): void
    {
        $first = Transaction::query()->orderBy('id')->firstOrFail();
        $this->settle('500');

        $this->expectException(CollectionException::class);
        app(ReversalService::class)->reverse($first, 'x', $this->admin);
    }

    public function test_rules(): void
    {
        $this->actingAs($this->rahim)->post(route('admin.kisti.settle', $this->loan), ['amount' => '100', 'receiver_id' => $this->rahim->id, 'reason' => 'x'])
            ->assertForbidden();
        $this->settle('1500')->assertSessionHasErrorsIn('settle', 'amount'); // more than the 1000 left
        $other = User::factory()->create();
        $this->settle('300', $other)->assertSessionHasErrorsIn('settle', 'amount'); // not admin / not this loan's collector
        $this->assertSame(AccountStatus::Active, $this->loan->fresh()->status);
    }
}
