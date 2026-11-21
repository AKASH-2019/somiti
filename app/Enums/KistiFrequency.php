<?php

namespace App\Enums;

enum KistiFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'দৈনিক',
            self::Weekly => 'সাপ্তাহিক',
        };
    }

    /** "১২০টি দৈনিক কিস্তি" style unit */
    public function unit(): string
    {
        return match ($this) {
            self::Daily => 'দিন',
            self::Weekly => 'সপ্তাহ',
        };
    }
}
