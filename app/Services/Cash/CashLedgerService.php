<?php

namespace App\Services\Cash;

use App\Enums\LedgerType;
use App\Models\CashLedgerEntry;
use App\Models\User;
use App\Support\Money;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** The only way to write the cash ledger. Call inside the same DB transaction as the business change. */
class CashLedgerService
{
    public function credit(User $holder, LedgerType $type, string $amount, ?Model $reference, string $description, ?User $by = null, ?DateTimeInterface $at = null, ?string $group = null): CashLedgerEntry
    {
        return $this->post($holder, $type, '0.00', $amount, $reference, $description, $by, $at, $group);
    }

    public function debit(User $holder, LedgerType $type, string $amount, ?Model $reference, string $description, ?User $by = null, ?DateTimeInterface $at = null, ?string $group = null): CashLedgerEntry
    {
        return $this->post($holder, $type, $amount, '0.00', $reference, $description, $by, $at, $group);
    }

    /** Current cash in hand, from the ledger only. */
    public function balance(User $holder): string
    {
        $row = CashLedgerEntry::query()->where('collector_id', $holder->id)
            ->selectRaw('COALESCE(SUM(credit),0) as c, COALESCE(SUM(debit),0) as d')->first();

        return Money::sub(number_format((float) $row->c, 2, '.', ''), number_format((float) $row->d, 2, '.', ''));
    }

    private function post(User $holder, LedgerType $type, string $debit, string $credit, ?Model $reference, string $description, ?User $by, ?DateTimeInterface $at, ?string $group): CashLedgerEntry
    {
        $debit = Money::fromPaisa(Money::toPaisa($debit));
        $credit = Money::fromPaisa(Money::toPaisa($credit));
        if ((Money::toPaisa($debit) > 0) === (Money::toPaisa($credit) > 0) || Money::toPaisa($debit) < 0 || Money::toPaisa($credit) < 0) {
            throw new InvalidArgumentException('A ledger row needs exactly one positive side.');
        }

        return CashLedgerEntry::query()->create([
            'collector_id' => $holder->id,
            'transaction_type' => $type,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'debit' => $debit,
            'credit' => $credit,
            'transaction_datetime' => $at ?? now(),
            'description' => $description,
            'entry_group' => $group,
            'created_by' => $by?->id,
        ]);
    }
}
