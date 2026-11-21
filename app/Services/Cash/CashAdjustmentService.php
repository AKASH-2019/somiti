<?php

namespace App\Services\Cash;

use App\Enums\LedgerType;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Admin-only correction of a cash holder's balance (e.g. a counting mistake). Always with a reason. */
class CashAdjustmentService
{
    public function __construct(private CashLedgerService $ledger, private AuditService $audit) {}

    public function adjust(User $holder, string $direction, string $amount, string $reason, User $admin): void
    {
        if (! $admin->isAdmin()) {
            throw new DomainException('শুধু এডমিন ক্যাশ সমন্বয় করতে পারেন।');
        }
        $amount = Money::fromPaisa(Money::toPaisa($amount));
        if (Money::toPaisa($amount) <= 0) {
            throw new DomainException('পরিমাণ শূন্যের বেশি হতে হবে।');
        }

        DB::transaction(function () use ($holder, $direction, $amount, $reason, $admin) {
            User::query()->whereKey($holder->id)->lockForUpdate()->first();
            $before = $this->ledger->balance($holder);
            $text = 'ক্যাশ সমন্বয় — '.$reason;
            $direction === 'in'
                ? $this->ledger->credit($holder, LedgerType::Adjustment, $amount, null, $text, $admin)
                : $this->ledger->debit($holder, LedgerType::Adjustment, $amount, null, $text, $admin);

            $this->audit->log('cash.adjusted', $holder, ['balance' => $before], [
                'direction' => $direction, 'amount' => $amount, 'reason' => $reason, 'balance' => $this->ledger->balance($holder),
            ], $admin->id);
        });
    }
}
