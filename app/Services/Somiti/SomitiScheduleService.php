<?php

namespace App\Services\Somiti;

use App\Enums\AccountStatus;
use App\Enums\InstallmentStatus;
use App\Models\Account;
use App\Models\Installment;
use App\Models\SomitiShareChange;
use App\Services\CalendarService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Creates Somiti daily installment rows.
 * Rows exist from the start date up to today (rolled daily), and further only when needed for an
 * advance payment (Phase 5 calls ensureThrough() with the last date being paid).
 * Rows are always contiguous over working days, so "last row + 1" is where generation resumes.
 */
class SomitiScheduleService
{
    public function __construct(private CalendarService $calendar) {}

    /** @return int number of rows created */
    public function ensureThrough(Account $account, CarbonInterface $until): int
    {
        return DB::transaction(function () use ($account, $until) {
            $account = Account::query()->with('somitiDetail')->lockForUpdate()->findOrFail($account->id);
            if ($account->status !== AccountStatus::Active || ! $account->somitiDetail) {
                return 0;
            }

            $last = Installment::query()->where('account_id', $account->id)->max('due_date');
            $from = $last ? Carbon::parse($last)->addDay() : $account->somitiDetail->start_date->copy();
            $days = $this->calendar->workingDays($from, $until);
            if ($days === []) {
                return 0;
            }

            $changes = $this->changes($account);
            $sequence = (int) Installment::query()->where('account_id', $account->id)->max('sequence_no');
            $now = now();
            $rows = [];
            foreach ($days as $day) {
                $rows[] = [
                    'account_id' => $account->id,
                    'sequence_no' => ++$sequence,
                    'due_date' => $day->toDateString(),
                    'amount_due' => $this->amountOn($changes, $day),
                    'amount_paid' => '0.00',
                    'status' => InstallmentStatus::Unpaid->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                Installment::query()->insert($chunk);
            }

            return count($rows);
        });
    }

    /** Rolls every active Somiti account up to $date. Runs at most once per day unless forced. */
    public function rollAll(CarbonInterface $date, bool $force = false): int
    {
        $key = 'somiti.rolled.'.$date->toDateString();
        if (! $force && Cache::has($key)) {
            return 0;
        }

        $created = 0;
        Account::query()->somiti()->where('status', AccountStatus::Active->value)
            ->select('id')->orderBy('id')
            ->each(function (Account $account) use ($date, &$created) {
                $created += $this->ensureThrough($account, $date);
            });

        Cache::put($key, true, $date->copy()->endOfDay());

        return $created;
    }

    /** Daily amount for a given date, from the account's share history. */
    public function dailyAmountOn(Account $account, CarbonInterface $date): string
    {
        return $this->amountOn($this->changes($account), $date);
    }

    /**
     * Admin added a holiday: remove that day's rows that have no money on them.
     * Days that already received money stay exactly as they are.
     */
    public function onHolidayAdded(CarbonInterface $date): int
    {
        return Installment::query()
            ->whereDate('due_date', $date->toDateString())
            ->where('amount_paid', 0)
            ->whereHas('account', fn ($q) => $q->somiti())
            ->delete();
    }

    /** Admin removed a (future) holiday: fill the gap for accounts already scheduled beyond it. */
    public function onHolidayRemoved(CarbonInterface $date): int
    {
        if (! $this->calendar->isWorkingDay($date)) {
            return 0; // still a weekly off-day
        }

        $created = 0;
        Account::query()->somiti()->where('status', AccountStatus::Active->value)
            ->whereHas('somitiDetail', fn ($q) => $q->whereDate('start_date', '<=', $date->toDateString()))
            ->whereHas('installments', fn ($q) => $q->whereDate('due_date', '>', $date->toDateString()))
            ->whereDoesntHave('installments', fn ($q) => $q->whereDate('due_date', $date->toDateString()))
            ->each(function (Account $account) use ($date, &$created) {
                DB::transaction(function () use ($account, $date, &$created) {
                    Account::query()->lockForUpdate()->find($account->id);
                    Installment::query()->create([
                        'account_id' => $account->id,
                        'sequence_no' => (int) Installment::query()->where('account_id', $account->id)->max('sequence_no') + 1,
                        'due_date' => $date->toDateString(),
                        'amount_due' => $this->dailyAmountOn($account, $date),
                        'amount_paid' => '0.00',
                        'status' => InstallmentStatus::Unpaid,
                    ]);
                    $created++;
                });
            });

        return $created;
    }

    /** @return list<array{date: string, amount: string}> sorted by date */
    private function changes(Account $account): array
    {
        return SomitiShareChange::query()->where('account_id', $account->id)
            ->orderBy('effective_date')->get(['effective_date', 'new_daily_amount'])
            ->map(fn ($c) => ['date' => $c->effective_date->toDateString(), 'amount' => (string) $c->new_daily_amount])
            ->all();
    }

    private function amountOn(array $changes, CarbonInterface $date): string
    {
        $amount = $changes[0]['amount'] ?? '0.00';
        $d = $date->toDateString();
        foreach ($changes as $change) {
            if ($change['date'] > $d) {
                break;
            }
            $amount = $change['amount'];
        }

        return $amount;
    }
}
