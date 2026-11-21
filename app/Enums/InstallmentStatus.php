<?php

namespace App\Enums;

use App\Support\Money;

enum InstallmentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'বকেয়া',
            self::Partial => 'আংশিক',
            self::Paid => 'পরিশোধিত',
        };
    }

    public static function for(string $amountDue, string $amountPaid): self
    {
        return match (true) {
            Money::toPaisa($amountPaid) <= 0 => self::Unpaid,
            Money::compare($amountPaid, $amountDue) >= 0 => self::Paid,
            default => self::Partial,
        };
    }
}
