<?php

namespace App\Services\Collection;

use App\Enums\AccountStatus;
use App\Enums\EntryType;
use App\Enums\InstallmentStatus;
use App\Enums\LedgerType;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Installment;
use App\Models\Transaction;
use App\Models\TransactionAllocation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Cash\CashLedgerService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a wrong collection without deleting anything:
 * the transaction is marked reversed, its allocations are marked reversed, the installments get their
 * money back off, and (for cash) the collector's ledger gets a matching debit.
 */
class ReversalService
{
    public function __construct(private CashLedgerService $ledger, private AuditService $audit) {}

    public function reverse(Transaction $tx, string $reason, User $actor): Transaction
    {
        if (! $actor->isAdmin()) {
            throw new CollectionException('শুধু এডমিন লেনদেন বাতিল করতে পারেন।');
        }

        return DB::transaction(function () use ($tx, $reason, $actor) {
            $tx = Transaction::query()->with('collector')->lockForUpdate()->findOrFail($tx->id);
            if ($tx->status === TransactionStatus::Reversed) {
                throw new CollectionException('এই লেনদেন আগেই বাতিল করা হয়েছে।');
            }
            $account = Account::query()->lockForUpdate()->findOrFail($tx->account_id);

            $allocations = TransactionAllocation::query()->where('transaction_id', $tx->id)->where('status', 'active')->get();
            foreach ($allocations as $alloc) {
                $row = Installment::query()->lockForUpdate()->findOrFail($alloc->installment_id);
                $paid = Money::sub((string) $row->amount_paid, (string) $alloc->amount);
                if (Money::toPaisa($paid) < 0) {
                    throw new CollectionException('হিসাবে অসঙ্গতি: কিস্তির জমা ঋণাত্মক হয়ে যাচ্ছে। এডমিনের সাথে যোগাযোগ করুন।');
                }
                $row->update(['amount_paid' => $paid, 'status' => InstallmentStatus::for((string) $row->amount_due, $paid)]);
                $alloc->update(['status' => 'reversed']);
            }

            $tx->forceFill([
                'status' => TransactionStatus::Reversed,
                'reversed_by' => $actor->id,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ])->save();

            if ($tx->entry_type === EntryType::Cash) {
                $this->ledger->debit($tx->collector, LedgerType::CollectionReversal, (string) $tx->amount, $tx,
                    "রসিদ {$tx->receipt_no} বাতিল — {$reason}", $actor);
            }

            // A loan closed by this payment is open again.
            if ($account->status === AccountStatus::Closed) {
                $account->forceFill(['status' => AccountStatus::Active, 'closed_at' => null])->save();
            }

            $this->audit->log('collection.reversed', $tx, ['status' => 'completed'], [
                'status' => 'reversed', 'reason' => $reason, 'amount' => (string) $tx->amount,
            ], $actor->id);

            return $tx;
        });
    }
}
