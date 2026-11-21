<?php

namespace App\Services\Collection;

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
use App\Models\Transaction;
use App\Models\TransactionAllocation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Cash\CashLedgerService;
use App\Services\NumberSequenceService;
use App\Services\Somiti\SomitiScheduleService;
use App\Support\BanglaFormatter;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The common payment engine for Somiti and Kisti.
 * One call = one Transaction + N allocations + installment updates + cash-ledger credit, all-or-nothing,
 * with the account and the chosen installments locked so two people cannot pay the same day twice.
 */
class CollectionService
{
    public function __construct(
        private NumberSequenceService $sequences,
        private SomitiScheduleService $somiti,
        private CashLedgerService $ledger,
        private AuditService $audit,
    ) {}

    /**
     * @param  list<string>  $keys  Somiti: dates (Y-m-d). Kisti: installment ids.
     */
    public function collect(
        Account $account,
        User $actor,
        array $keys,
        string $amount,
        string $idempotencyKey,
        ?string $notes = null,
        EntryType $entryType = EntryType::Cash,
        ?Carbon $collectedAt = null,
    ): Transaction {
        if ($existing = Transaction::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing; // double-click / resubmit: hand back the first result
        }

        $amountPaisa = Money::toPaisa($amount);
        if ($amountPaisa <= 0) {
            throw new CollectionException('টাকার পরিমাণ শূন্যের বেশি হতে হবে।');
        }
        if ($entryType === EntryType::Historical) {
            if (! $actor->isAdmin()) {
                throw new CollectionException('পুরনো খাতার এন্ট্রি শুধু এডমিন করতে পারেন।');
            }
            if (! $collectedAt || $collectedAt->gt(now())) {
                throw new CollectionException('পুরনো খাতার এন্ট্রির জন্য আজ বা আগের একটি তারিখ দিন।');
            }
        } else {
            $collectedAt = now(); // cash is received now; collectors cannot backdate
        }
        $keys = array_values(array_unique(array_map('strval', $keys)));
        if ($keys === []) {
            throw new CollectionException('অন্তত একটি দিন বা কিস্তি নির্বাচন করুন।');
        }

        return DB::transaction(function () use ($account, $actor, $keys, $amount, $amountPaisa, $idempotencyKey, $notes, $entryType, $collectedAt) {
            $account = Account::query()->with(['member', 'somitiDetail', 'kistiDetail'])->lockForUpdate()->findOrFail($account->id);

            if ($account->status !== AccountStatus::Active) {
                throw new CollectionException('এই অ্যাকাউন্টটি চালু নেই — টাকা নেওয়া যাবে না।');
            }
            if (! $account->member->isActive()) {
                throw new CollectionException('সদস্য নিষ্ক্রিয় — টাকা নেওয়া যাবে না।');
            }
            if (! $actor->isAdmin() && $account->assigned_collector_id !== $actor->id) {
                throw new CollectionException('এই অ্যাকাউন্ট আপনার দায়িত্বে নেই।');
            }
            if (Transaction::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                return Transaction::query()->where('idempotency_key', $idempotencyKey)->first();
            }

            $rows = $account->type === AccountType::Somiti
                ? $this->somitiRows($account, $keys, $entryType)
                : $this->kistiRows($account, $keys);

            $plan = $account->type === AccountType::Somiti
                ? $this->planSomiti($rows, $amountPaisa)
                : $this->planKisti($rows, $amountPaisa);

            $tx = Transaction::query()->create([
                'receipt_no' => $this->sequences->next('receipt', 'RCP'),
                'idempotency_key' => $idempotencyKey,
                'account_id' => $account->id,
                'member_id' => $account->member_id,
                'collector_id' => $actor->id,
                'entry_type' => $entryType,
                'payment_method' => PaymentMethod::Cash,
                'amount' => Money::fromPaisa($amountPaisa),
                'collected_at' => $collectedAt,
                'status' => TransactionStatus::Completed,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            $collectedDate = $collectedAt->toDateString();
            foreach ($plan as [$row, $paisa]) {
                /** @var Installment $row */
                $date = $row->due_date->toDateString();
                TransactionAllocation::query()->create([
                    'transaction_id' => $tx->id,
                    'installment_id' => $row->id,
                    'covered_date' => $date,
                    'amount' => Money::fromPaisa($paisa),
                    'timing' => $date < $collectedDate ? AllocationTiming::Previous : ($date === $collectedDate ? AllocationTiming::Current : AllocationTiming::Advance),
                    'status' => 'active',
                ]);
                $paid = Money::add((string) $row->amount_paid, Money::fromPaisa($paisa));
                $row->update(['amount_paid' => $paid, 'status' => InstallmentStatus::for((string) $row->amount_due, $paid)]);
            }

            if ($entryType === EntryType::Cash) {
                $this->ledger->credit($actor, LedgerType::Collection, Money::fromPaisa($amountPaisa), $tx,
                    "রসিদ {$tx->receipt_no} — {$account->member->name} ({$account->account_no})", $actor, $collectedAt);
            }

            if ($account->type === AccountType::Kisti && ! $this->hasUnpaid($account)) {
                $account->forceFill(['status' => AccountStatus::Closed, 'closed_at' => $collectedAt->toDateString()])->save();
            }

            $this->audit->log('collection.created', $tx, [], [
                'receipt_no' => $tx->receipt_no, 'account' => $account->account_no, 'amount' => $tx->amount,
                'entry_type' => $entryType->value, 'covered' => array_map(fn ($p) => $p[0]->due_date->toDateString(), $plan),
            ], $actor->id);

            return $tx;
        });
    }

    /** @return Collection<int, Installment> */
    private function somitiRows(Account $account, array $dates, EntryType $entryType): Collection
    {
        foreach ($dates as $d) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                throw new CollectionException('তারিখ সঠিক নয়।');
            }
        }
        sort($dates);
        $max = Carbon::parse(end($dates));
        if ($max->gt(today()->addDays(366))) {
            throw new CollectionException('এক বছরের বেশি অগ্রিম নেওয়া যাবে না।');
        }
        if ($entryType === EntryType::Historical && $max->gt(today())) {
            throw new CollectionException('পুরনো খাতার এন্ট্রিতে ভবিষ্যতের তারিখ নেওয়া যাবে না।');
        }

        $this->somiti->ensureThrough($account, $max);

        $rows = Installment::query()->where('account_id', $account->id)->whereIn('due_date', $dates)
            ->orderBy('due_date')->lockForUpdate()->get();
        if ($rows->count() !== count($dates)) {
            $found = $rows->map(fn ($r) => $r->due_date->toDateString())->all();
            $bad = array_values(array_diff($dates, $found))[0];
            throw new CollectionException(BanglaFormatter::date($bad).' তারিখে কোনো জমা নির্ধারিত নেই (ছুটির দিন অথবা সঞ্চয় শুরুর আগে)।');
        }

        return $rows;
    }

    /** @return Collection<int, Installment> */
    private function kistiRows(Account $account, array $ids): Collection
    {
        if (array_filter($ids, fn ($id) => ! ctype_digit($id))) {
            throw new CollectionException('কিস্তি নির্বাচন সঠিক নয়।');
        }
        $rows = Installment::query()->where('account_id', $account->id)->whereIn('id', $ids)
            ->orderBy('sequence_no')->lockForUpdate()->get();
        if ($rows->count() !== count($ids)) {
            throw new CollectionException('নির্বাচিত কিস্তি এই অ্যাকাউন্টের নয়।');
        }

        return $rows;
    }

    /** Somiti: whole days only — the amount must equal exactly what the chosen days still need. */
    private function planSomiti(Collection $rows, int $amount): array
    {
        $plan = [];
        $need = 0;
        foreach ($rows as $r) {
            $left = Money::toPaisa((string) $r->amount_due) - Money::toPaisa((string) $r->amount_paid);
            if ($left <= 0) {
                throw new CollectionException(BanglaFormatter::date($r->due_date).' তারিখের জমা আগেই পরিশোধিত।');
            }
            $plan[] = [$r, $left];
            $need += $left;
        }
        if ($need !== $amount) {
            throw new CollectionException('নির্বাচিত '.BanglaFormatter::digits(count($plan)).' দিনের জন্য ঠিক '
                .BanglaFormatter::money(Money::fromPaisa($need)).' নিতে হবে। সমিতিতে আংশিক দিনের জমা নেওয়া হয় না।');
        }

        return $plan;
    }

    /** Kisti: fill the chosen installments in order; only the last one may be part-paid. */
    private function planKisti(Collection $rows, int $amount): array
    {
        $plan = [];
        $left = $amount;
        foreach ($rows as $r) {
            $open = Money::toPaisa((string) $r->amount_due) - Money::toPaisa((string) $r->amount_paid);
            if ($open <= 0) {
                throw new CollectionException('কিস্তি নং '.BanglaFormatter::digits($r->sequence_no).' আগেই পরিশোধিত।');
            }
            if ($left <= 0) {
                throw new CollectionException('টাকার তুলনায় বেশি কিস্তি নির্বাচন করা হয়েছে। কিস্তি নং '
                    .BanglaFormatter::digits($r->sequence_no).' এর জন্য কোনো টাকা থাকছে না।');
            }
            $take = min($open, $left);
            $plan[] = [$r, $take];
            $left -= $take;
        }
        if ($left > 0) {
            throw new CollectionException('নির্বাচিত কিস্তিগুলোর বাকি টাকার চেয়ে '.BanglaFormatter::money(Money::fromPaisa($left))
                .' বেশি দেওয়া হয়েছে। আরও কিস্তি নির্বাচন করুন অথবা টাকা কমান।');
        }

        return $plan;
    }

    private function hasUnpaid(Account $account): bool
    {
        return Installment::query()->where('account_id', $account->id)
            ->whereColumn('amount_paid', '<', 'amount_due')->exists();
    }
}
