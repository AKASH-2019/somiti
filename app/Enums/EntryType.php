<?php

namespace App\Enums;

/**
 * cash       — money received now by the logged-in user; credited to their cash ledger.
 * historical — admin entry of a payment from the old paper register, with its real past date;
 *              updates the member's dues but is NOT cash in anyone's hand (no ledger entry).
 */
enum EntryType: string
{
    case Cash = 'cash';
    case Historical = 'historical';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'নগদ সংগ্রহ',
            self::Historical => 'পুরনো খাতার এন্ট্রি',
        };
    }
}
