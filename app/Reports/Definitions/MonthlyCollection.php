<?php

namespace App\Reports\Definitions;

class MonthlyCollection extends PeriodCollection
{
    public function key(): string { return 'monthly'; }

    public function title(): string { return 'মাসিক সংগ্রহ'; }

    public function description(): string { return 'মাসের প্রতিদিনের সমিতি ও কিস্তি সংগ্রহ'; }

    public function period(): string { return 'month'; }
}
