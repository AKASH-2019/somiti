<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Completed = 'completed';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'সম্পন্ন',
            self::Reversed => 'বাতিলকৃত',
        };
    }
}
