<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Working-day rules used by Somiti and daily Kisti schedules (Phases 3–4).
 * Off-days = admin-maintained weekly off-days + admin-maintained holiday dates.
 */
class CalendarService
{
    public function __construct(private SettingService $settings) {}

    public function isHoliday(CarbonInterface $date): bool
    {
        return Holiday::query()->whereDate('date', $date->toDateString())->exists();
    }

    public function isWeeklyOff(CarbonInterface $date): bool
    {
        return in_array($date->dayOfWeek, $this->settings->weeklyOffDays(), true);
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return ! $this->isWeeklyOff($date) && ! $this->isHoliday($date);
    }

    /** Holiday dates (Y-m-d => title) in a range, for bulk schedule generation. */
    public function holidaysBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        return Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->mapWithKeys(fn (Holiday $h) => [$h->date->toDateString() => $h->title])
            ->all();
    }

    /**
     * Working days (weekly off-days and holidays excluded) from $from to $to inclusive.
     *
     * @return list<\Illuminate\Support\Carbon>
     */
    public function workingDays(CarbonInterface $from, CarbonInterface $to): array
    {
        if ($from->gt($to)) {
            return [];
        }
        $off = $this->settings->weeklyOffDays();
        $holidays = $this->holidaysBetween($from, $to);
        $days = [];

        for ($d = Carbon::parse($from->toDateString()); $d->lte($to); $d->addDay()) {
            if (! in_array($d->dayOfWeek, $off, true) && ! isset($holidays[$d->toDateString()])) {
                $days[] = $d->copy();
            }
        }

        return $days;
    }

    public function todayStatus(): array
    {
        $today = Carbon::today();
        $holiday = Holiday::query()->whereDate('date', $today->toDateString())->first();

        return [
            'date' => $today,
            'working' => ! $holiday && ! $this->isWeeklyOff($today),
            'reason' => $holiday?->title ?? ($this->isWeeklyOff($today) ? 'সাপ্তাহিক ছুটি' : null),
        ];
    }
}
