<?php

namespace App\Reports\Definitions;

class WeeklyCollection extends PeriodCollection
{
    public function key(): string { return 'weekly'; }

    public function title(): string { return 'সাপ্তাহিক সংগ্রহ'; }

    public function description(): string { return 'সাত দিনের দিনভিত্তিক সমিতি ও কিস্তি সংগ্রহ'; }

    public function period(): string { return 'range'; }

    public function defaultPeriod(): array { return [today()->subDays(6), today()]; }
}
