<?php

namespace App\Enums;

enum HandoverStatus: string
{
    case Pending = 'pending';
    case Received = 'received';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'অপেক্ষমাণ',
            self::Received => 'গৃহীত',
            self::Rejected => 'প্রত্যাখ্যাত',
            self::Cancelled => 'বাতিল',
            self::Disputed => 'বিরোধপূর্ণ',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Received => 'success',
            self::Rejected, self::Disputed => 'danger',
            self::Cancelled => 'muted',
        };
    }

    /** Statuses whose amount is still held back from the sender's available cash. */
    public static function reserving(): array
    {
        return [self::Pending->value, self::Disputed->value];
    }
}
