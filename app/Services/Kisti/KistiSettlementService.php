<?php

namespace App\Services\Kisti;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\AllocationTiming;
use App\Enums\EntryType;
use App\Enums\InstallmentStatus;
use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Installment;
use App\Models\KistiSettlement;
use App\Models\Transaction;
use App\Models\TransactionAllocation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Cash\CashLedgerService;
use App\Services\NumberSequenceService;
use App\Support\BanglaFormatter;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Settle (close) a loan early for a smaller one-time amount.
 * The amount is collected like any payment (oldest installments first, receipt, cash ledger);
 * whatever is still open after that is waived and the loan is closed as "সেটেলমেন্টে বন্ধ".
 */
class KistiSettlementService
{
    public function __construct(
        private NumberSequenceService $sequences,
        private CashLedgerService $ledger,
        private AuditService $audit,
    ) {}

    public function outstanding(Account $account): string
    {
        $row = Installment::query()->where('account_id', $account->id)
            ->selectRaw('COALESCE(SUM(amount_due - amount_paid),0) as left_amount')->first();

        return number_format((float) $row->left_amount, 2, '.', '');
    }

    public function settle(Account $account, string $amount, User $receiver, string $reason, User $actor): KistiSettlement
    {
        if (! $actor->isAdmin()) {
            throw new DomainException('শুধু এডমিন সেটেলমেন্ট করতে পারেন।');
        }

        return DB::transaction(function () use ($account, $amount, $receiver, $reason, $actor) {
            $account = Account::query()->with(['member', 'kistiDetail'])->lockForUpdate()->findOrFail($account->id);
            if ($account->type !== AccountType::Kisti || $account->status !== AccountStatus::Active) {
                throw new DomainException('শুধু চালু কিস্তি অ্যাকাউন্টে সেটেলমেন্ট করা যায়।');
            }
            if (! $receiver->is_active || ! ($receiver->isAdmin() || $receiver->id === $account->assigned_collector_id)) {
                throw new DomainException('টাকা গ্রহণকারী হতে পারেন এডমিন অথবা এই অ্যাকাউন্টের কালেক্টর।');
            }

            $rows = Installment::query()->where('account_id', $account->id)->orderBy('sequence_no')->lockForUpdate()->get();
            $open = fn (Installment $r) => Money::toPaisa((string) $r->amount_due) - Money::toPaisa((string) $r->amount_paid);
            $outstanding = $rows->sum($open);
            $take = Money::toPaisa($amount);

            if ($take <= 0) {
                throw new DomainException('সেটেলমেন্টের টাকা শূন্যের বেশি হতে হবে।');
            }
            if ($take > $outstanding) {
                throw new DomainException('সেটেলমেন্টের টাকা বাকি ঋণ '.BanglaFormatter::money(Money::fromPaisa($outstanding)).'-এর বেশি হতে পারবে না।');
            }

            $waived = $outstanding - $take;
            $now = now();

            $tx = Transaction::query()->create([
                'receipt_no' => $this->sequences->next('receipt', 'RCP'),
                'idempotency_key' => (string) Str::uuid(),
                'account_id' => $account->id,
                'member_id' => $account->member_id,
                'collector_id' => $receiver->id,
                'entry_type' => EntryType::Cash,
                'payment_method' => PaymentMethod::Cash,
                'amount' => Money::fromPaisa($take),
                'collected_at' => $now,
                'status' => TransactionStatus::Completed,
                'notes' => 'সেটেলমেন্ট — মওকুফ '.BanglaFormatter::money(Money::fromPaisa($waived)).'। '.$reason,
                'created_by' => $actor->id,
            ]);

            // 1) The money received pays the oldest open installments first.
            $left = $take;
            $today = $now->toDateString();
            foreach ($rows as $r) {
                $need = $open($r);
                if ($need <= 0 || $left <= 0) {
                    continue;
                }
                $pay = min($need, $left);
                $left -= $pay;
                $date = $r->due_date->toDateString();
                TransactionAllocation::query()->create([
                    'transaction_id' => $tx->id,
                    'installment_id' => $r->id,
                    'covered_date' => $date,
                    'amount' => Money::fromPaisa($pay),
                    'timing' => $date < $today ? AllocationTiming::Previous : ($date === $today ? AllocationTiming::Current : AllocationTiming::Advance),
                    'status' => 'active',
                ]);
                $r->amount_paid = Money::add((string) $r->amount_paid, Money::fromPaisa($pay));
            }

            // 2) Whatever is still open is waived.
            foreach ($rows as $r) {
                $still = Money::toPaisa((string) $r->amount_due) - Money::toPaisa((string) $r->amount_paid);
                if ($still > 0) {
                    $r->amount_waived = Money::add((string) $r->amount_waived, Money::fromPaisa($still));
                    $r->amount_due = (string) $r->amount_paid;
                }
                if ($r->isDirty()) {
                    $r->status = InstallmentStatus::for((string) $r->amount_due, (string) $r->amount_paid);
                    $r->save();
                }
            }

            $this->ledger->credit($receiver, LedgerType::Collection, Money::fromPaisa($take), $tx,
                "রসিদ {$tx->receipt_no} — সেটেলমেন্ট, {$account->member->name} ({$account->account_no})", $actor, $now);

            $settlement = KistiSettlement::query()->create([
                'account_id' => $account->id,
                'transaction_id' => $tx->id,
                'outstanding_before' => Money::fromPaisa($outstanding),
                'settled_amount' => Money::fromPaisa($take),
                'waived_amount' => Money::fromPaisa($waived),
                'reason' => $reason,
                'receiver_id' => $receiver->id,
                'settled_by' => $actor->id,
                'status' => 'active',
            ]);

            $account->forceFill(['status' => AccountStatus::Settled, 'closed_at' => $today])->save();

            $this->audit->log('kisti.settled', $account, ['status' => 'active', 'outstanding' => Money::fromPaisa($outstanding)], [
                'status' => 'settled', 'received' => Money::fromPaisa($take), 'waived' => Money::fromPaisa($waived),
                'receipt_no' => $tx->receipt_no, 'reason' => $reason,
            ], $actor->id);

            return $settlement;
        });
    }

    /**
     * Called by ReversalService inside its DB transaction, BEFORE the settlement payment's allocations are reversed:
     * puts the waived amounts back onto the installments and re-opens the loan.
     */
    public function undo(KistiSettlement $settlement, User $actor): void
    {
        Installment::query()->where('account_id', $settlement->account_id)->where('amount_waived', '>', 0)
            ->lockForUpdate()->get()
            ->each(function (Installment $r) {
                $due = Money::add((string) $r->amount_due, (string) $r->amount_waived);
                $r->update([
                    'amount_due' => $due,
                    'amount_waived' => '0.00',
                    'status' => InstallmentStatus::for($due, (string) $r->amount_paid),
                ]);
            });

        $settlement->forceFill(['status' => 'reversed', 'reversed_by' => $actor->id, 'reversed_at' => now()])->save();
        Account::query()->whereKey($settlement->account_id)->update(['status' => AccountStatus::Active->value, 'closed_at' => null]);
        $this->audit->log('kisti.settlement_reversed', $settlement->account, [], ['settlement_id' => $settlement->id], $actor->id);
    }
}
