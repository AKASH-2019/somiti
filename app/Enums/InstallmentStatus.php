<?php

namespace App\Enums;

use App\Support\Money;

enum InstallmentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'বকেয়া',
            self::Partial => 'আংশিক',
            self::Paid => 'পরিশোধিত',
            self::Waived => 'মওকুফ',
        };
    }

    public static function for(string $amountDue, string $amountPaid): self
    {
        return match (true) {
            Money::toPaisa($amountDue) <= 0 => self::Waived, // only after a settlement
            Money::toPaisa($amountPaid) <= 0 => self::Unpaid,
            Money::compare($amountPaid, $amountDue) >= 0 => self::Paid,
            default => self::Partial,
        };
    }
}
