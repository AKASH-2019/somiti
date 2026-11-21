<?php

namespace App\Enums;

/** Closing / withdrawal is out of scope for now; the status column leaves room for it. */
enum AccountStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'চালু',
            self::Closed => 'বন্ধ',
            self::Cancelled => 'বাতিল',
        };
    }
}
