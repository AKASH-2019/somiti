<?php

namespace App\Services\Collection;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Installment;
use App\Services\CalendarService;
use App\Services\Somiti\SomitiScheduleService;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Everything shown on the collection screen, oldest first.
 * Each item: key (date for Somiti, installment id for Kisti), date, label, sequence, remaining, partial, paid, kind.
 *  - paid = true  → shown greyed with ✓ for reference; cannot be selected (the server refuses it too).
 *  - kind         → previous | current | advance (compared with today).
 */
class CollectionOptionsService
{
    /** How far ahead the screen offers Somiti advance days. */
    public const ADVANCE_DAYS = 92;

    /** How far back already-paid Somiti days are shown (unpaid past days are always shown). */
    public const PAID_HISTORY_DAYS = 62;

    public function __construct(private SomitiScheduleService $somiti, private CalendarService $calendar) {}

    public function forAccount(Account $account): array
    {
        return $account->type === AccountType::Somiti ? $this->somiti($account) : $this->kisti($account);
    }

    private function somiti(Account $account): array
    {
        $today = today();
        $historyFrom = $today->copy()->subDays(self::PAID_HISTORY_DAYS)->toDateString();
        $this->somiti->ensureThrough($account, $today);
        $rows = Installment::query()->where('account_id', $account->id)->orderBy('due_date')->get()
            ->keyBy(fn ($r) => $r->due_date->toDateString());

        $items = [];
        foreach ($rows as $date => $r) {
            if ($date > $today->toDateString()) {
                break;
            }
            $remaining = Money::sub((string) $r->amount_due, (string) $r->amount_paid);
            $paid = Money::toPaisa($remaining) <= 0;
            if ($paid && $date < $historyFrom) {
                continue; // keep the list short: old paid days are on the account calendar
            }
            $this->push($items, $date, $date, $remaining, $today, null, ! $paid && Money::toPaisa((string) $r->amount_paid) > 0, $paid, (string) $r->amount_paid);
        }

        foreach ($this->calendar->workingDays($today->copy()->addDay(), $today->copy()->addDays(self::ADVANCE_DAYS)) as $day) {
            $date = $day->toDateString();
            if ($day->lt($account->somitiDetail->start_date)) {
                continue;
            }
            if (isset($rows[$date])) {
                $remaining = Money::sub((string) $rows[$date]->amount_due, (string) $rows[$date]->amount_paid);
                $paid = Money::toPaisa($remaining) <= 0;
                $this->push($items, $date, $date, $remaining, $today, null, ! $paid && Money::toPaisa((string) $rows[$date]->amount_paid) > 0, $paid, (string) $rows[$date]->amount_paid);
            } else {
                $this->push($items, $date, $date, $this->somiti->dailyAmountOn($account, $day), $today);
            }
        }

        return $items;
    }

    /** Kisti: the complete schedule, paid ones included. */
    private function kisti(Account $account): array
    {
        $today = today();
        $items = [];
        Installment::query()->where('account_id', $account->id)->orderBy('sequence_no')->get()
            ->each(function (Installment $r) use (&$items, $today) {
                $remaining = Money::sub((string) $r->amount_due, (string) $r->amount_paid);
                $paid = Money::toPaisa($remaining) <= 0;
                $this->push($items, (string) $r->id, $r->due_date->toDateString(), $remaining, $today, $r->sequence_no,
                    ! $paid && Money::toPaisa((string) $r->amount_paid) > 0, $paid, (string) $r->amount_paid);
            });

        return $items;
    }

    private function push(array &$items, string $key, string $date, string $remaining, Carbon $today, ?int $sequence = null, bool $partial = false, bool $paid = false, string $paidAmount = '0.00'): void
    {
        $d = $today->toDateString();
        $items[] = [
            'key' => $key,
            'date' => $date,
            'label' => bn_date($date, true),
            'sequence' => $sequence,
            'remaining' => $paid ? '0.00' : $remaining,
            'paid_amount' => $paidAmount,
            'partial' => $partial,
            'paid' => $paid,
            'kind' => $date < $d ? 'previous' : ($date === $d ? 'current' : 'advance'),
        ];
    }
}
