<?php

namespace App\Enums;

/** Covered date compared with the date the money was received. */
enum AllocationTiming: string
{
    case Previous = 'previous';
    case Current = 'current';
    case Advance = 'advance';

    public function label(): string
    {
        return match ($this) {
            self::Previous => 'পূর্ববর্তী বকেয়া',
            self::Current => 'চলতি',
            self::Advance => 'অগ্রিম',
        };
    }
}
