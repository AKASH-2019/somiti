<?php

namespace App\Reports\Definitions;

class DailyKistiCollection extends DailyCollection
{
    public function key(): string { return 'daily-kisti'; }

    public function title(): string { return 'দৈনিক কিস্তি সংগ্রহ'; }

    public function description(): string { return 'একদিনের সব কিস্তি আদায়ের রসিদ'; }

    protected function accountType(): string { return 'kisti'; }
}
