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
 * What can be paid on an account right now, oldest first.
 * Each item: key (date for Somiti, installment id for Kisti), date, label, remaining, kind (previous|current|advance).
 */
class CollectionOptionsService
{
    /** How far ahead the collection screen offers Somiti advance days. */
    public const ADVANCE_DAYS = 92;

    public function __construct(private SomitiScheduleService $somiti, private CalendarService $calendar) {}

    public function forAccount(Account $account): array
    {
        return $account->type === AccountType::Somiti ? $this->somiti($account) : $this->kisti($account);
    }

    private function somiti(Account $account): array
    {
        $today = today();
        $this->somiti->ensureThrough($account, $today);
        $rows = Installment::query()->where('account_id', $account->id)->orderBy('due_date')->get()
            ->keyBy(fn ($r) => $r->due_date->toDateString());

        $items = [];
        foreach ($rows as $date => $r) {
            if ($date > $today->toDateString()) {
                break;
            }
            $this->push($items, $date, $date, Money::sub((string) $r->amount_due, (string) $r->amount_paid), $today);
        }

        // Future working days: existing advance rows (if not fully paid) or planned days at the scheduled rate.
        foreach ($this->calendar->workingDays($today->copy()->addDay(), $today->copy()->addDays(self::ADVANCE_DAYS)) as $day) {
            $date = $day->toDateString();
            if ($day->lt($account->somitiDetail->start_date)) {
                continue;
            }
            $remaining = isset($rows[$date])
                ? Money::sub((string) $rows[$date]->amount_due, (string) $rows[$date]->amount_paid)
                : $this->somiti->dailyAmountOn($account, $day);
            $this->push($items, $date, $date, $remaining, $today);
        }

        return $items;
    }

    private function kisti(Account $account): array
    {
        $today = today();
        $items = [];
        Installment::query()->where('account_id', $account->id)->orderBy('sequence_no')->get()
            ->each(function (Installment $r) use (&$items, $today) {
                $this->push($items, (string) $r->id, $r->due_date->toDateString(),
                    Money::sub((string) $r->amount_due, (string) $r->amount_paid), $today, $r->sequence_no,
                    Money::toPaisa((string) $r->amount_paid) > 0);
            });

        return $items;
    }

    private function push(array &$items, string $key, string $date, string $remaining, Carbon $today, ?int $sequence = null, bool $partial = false): void
    {
        if (Money::toPaisa($remaining) <= 0) {
            return;
        }
        $d = $today->toDateString();
        $items[] = [
            'key' => $key,
            'date' => $date,
            'label' => bn_date($date, true),
            'sequence' => $sequence,
            'remaining' => $remaining,
            'partial' => $partial,
            'kind' => $date < $d ? 'previous' : ($date === $d ? 'current' : 'advance'),
        ];
    }
}
