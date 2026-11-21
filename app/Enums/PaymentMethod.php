<?php

namespace App\Enums;

/** Members pay in cash only. Kept as an enum so other methods can be added later without a schema change. */
enum PaymentMethod: string
{
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'নগদ',
        };
    }
}
