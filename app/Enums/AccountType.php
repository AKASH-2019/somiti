<?php

namespace App\Enums;

enum AccountType: string
{
    case Somiti = 'somiti';
    case Kisti = 'kisti';

    public function label(): string
    {
        return match ($this) {
            self::Somiti => 'সমিতি',
            self::Kisti => 'কিস্তি',
        };
    }
}
