<?php

namespace App\Reports\Definitions;

class DailySomitiCollection extends DailyCollection
{
    public function key(): string { return 'daily-somiti'; }

    public function title(): string { return 'দৈনিক সমিতি সংগ্রহ'; }

    public function description(): string { return 'একদিনের সব সমিতি জমার রসিদ'; }

    protected function accountType(): string { return 'somiti'; }
}
