<?php

namespace App\Services\Kisti;

use App\Enums\AccountStatus;
use App\Enums\InstallmentStatus;
use App\Enums\KistiFrequency;
use App\Models\Account;
use App\Models\Installment;
use App\Models\KistiAccountDetail;
use App\Services\CalendarService;
use App\Services\SettingService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kisti schedules are generated in full when the loan is created.
 *  - Daily: one installment per working day from the first installment date.
 *  - Weekly: first date + 7 days each time; a date on an off-day moves to the next working day.
 * When the calendar changes, reflow() moves only installments that are unpaid AND due today or later.
 * Paid (or part-paid) installments and past dues never move.
 */
class KistiScheduleService
{
    private const HORIZON_YEARS = 6;

    /** @var array<string, true> */
    private array $holidays = [];

    /** @var int[] */
    private array $offDays = [];

    public function __construct(private CalendarService $calendar, private SettingService $settings) {}

    /** @return list<Carbon> */
    public function plan(KistiFrequency $frequency, CarbonInterface $first, int $count): array
    {
        $this->loadCalendar($first);
        $dates = [];
        $prev = null;

        for ($i = 0; $i < $count; $i++) {
            $d = $frequency === KistiFrequency::Daily
                ? ($prev ? $prev->copy()->addDay() : Carbon::parse($first->toDateString()))
                : Carbon::parse($first->toDateString())->addWeeks($i);
            if ($prev && $d->lte($prev)) {
                $d = $prev->copy()->addDay();
            }
            $dates[] = $prev = $this->nextWorkingDay($d);
        }

        return $dates;
    }

    public function generate(Account $account): void
    {
        $detail = $account->kistiDetail;
        $dates = $this->plan($detail->frequency, $detail->first_installment_date, $detail->total_installments);
        $now = now();
        $rows = [];
        foreach ($dates as $i => $date) {
            $last = $i === count($dates) - 1;
            $rows[] = [
                'account_id' => $account->id,
                'sequence_no' => $i + 1,
                'due_date' => $date->toDateString(),
                'amount_due' => (string) ($last ? $detail->last_installment_amount : $detail->installment_amount),
                'amount_paid' => '0.00',
                'status' => InstallmentStatus::Unpaid->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Installment::query()->insert($chunk);
        }
    }

    /** Re-dates movable installments of one account. Returns how many moved. */
    public function reflow(Account $account): int
    {
        return DB::transaction(function () use ($account) {
            $account = Account::query()->with('kistiDetail')->lockForUpdate()->findOrFail($account->id);
            if ($account->status !== AccountStatus::Active || ! $account->kistiDetail) {
                return 0;
            }
            $detail = $account->kistiDetail;
            $today = today();
            $this->loadCalendar($detail->first_installment_date->lt($today) ? $detail->first_installment_date : $today);

            $rows = Installment::query()->where('account_id', $account->id)->orderBy('sequence_no')->lockForUpdate()->get();
            $isFixed = fn (Installment $r) => Money::toPaisa((string) $r->amount_paid) > 0 || $r->due_date->lt($today);
            $taken = [];
            foreach ($rows as $r) {
                if ($isFixed($r)) {
                    $taken[$r->due_date->toDateString()] = true;
                }
            }

            $new = [];
            $cursor = Carbon::parse($detail->first_installment_date->toDateString());
            $prev = null;
            foreach ($rows as $index => $r) {
                if ($isFixed($r)) {
                    $prev = $r->due_date->copy();
                    $cursor = $cursor->max($r->due_date->copy()->addDay());

                    continue;
                }
                $d = $detail->frequency === KistiFrequency::Daily
                    ? $cursor->copy()
                    : Carbon::parse($detail->first_installment_date->toDateString())->addWeeks($index);
                if ($d->lt($today)) {
                    $d = $today->copy();
                }
                if ($prev && $d->lte($prev)) {
                    $d = $prev->copy()->addDay();
                }
                while (! $this->isWorking($d) || isset($taken[$d->toDateString()])) {
                    $d->addDay();
                }
                $new[$r->id] = $d->toDateString();
                $prev = $d->copy();
                $cursor = $d->copy()->addDay();
            }

            $changed = array_filter($new, fn ($date, $id) => $rows->firstWhere('id', $id)->due_date->toDateString() !== $date, ARRAY_FILTER_USE_BOTH);
            if ($changed === []) {
                return 0;
            }

            // Two passes so the unique (account_id, due_date) index never sees a temporary clash.
            $park = Carbon::parse('2999-01-01');
            foreach (array_keys($changed) as $n => $id) {
                Installment::query()->whereKey($id)->update(['due_date' => $park->copy()->addDays($n)->toDateString()]);
            }
            foreach ($changed as $id => $date) {
                Installment::query()->whereKey($id)->update(['due_date' => $date, 'updated_at' => now()]);
            }

            return count($changed);
        });
    }

    /** Calendar changed (holiday added/removed, weekly off-days edited): reflow affected loans. */
    public function reflowAll(?CarbonInterface $from = null): int
    {
        $from = ($from && $from->gt(today())) ? $from : today();
        $moved = 0;
        Account::query()->kisti()->where('status', AccountStatus::Active->value)
            ->whereHas('installments', fn ($q) => $q->where('amount_paid', 0)->whereDate('due_date', '>=', $from->toDateString()))
            ->orderBy('id')
            ->each(function (Account $account) use (&$moved) {
                $moved += $this->reflow($account);
            });

        return $moved;
    }

    private function loadCalendar(CarbonInterface $from): void
    {
        $this->offDays = $this->settings->weeklyOffDays();
        if (count($this->offDays) >= 7) {
            throw new RuntimeException('Every day of the week is marked as an off-day.');
        }
        $this->holidays = array_fill_keys(array_keys($this->calendar->holidaysBetween(
            $from, Carbon::parse($from->toDateString())->addYears(self::HORIZON_YEARS))), true);
    }

    private function isWorking(Carbon $d): bool
    {
        return ! in_array($d->dayOfWeek, $this->offDays, true) && ! isset($this->holidays[$d->toDateString()]);
    }

    private function nextWorkingDay(Carbon $d): Carbon
    {
        $d = $d->copy();
        while (! $this->isWorking($d)) {
            $d->addDay();
        }

        return $d;
    }
}
