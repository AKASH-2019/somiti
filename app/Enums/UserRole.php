<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Collector = 'collector';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'এডমিন',
            self::Collector => 'কালেক্টর',
        };
    }
}
