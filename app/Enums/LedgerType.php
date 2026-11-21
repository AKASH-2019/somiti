<?php

namespace App\Enums;

/**
 * Collector cash ledger, from the cash holder's point of view:
 * credit = cash came into their hand, debit = cash left their hand.
 */
enum LedgerType: string
{
    case Opening = 'opening';
    case Collection = 'collection';
    case CollectionReversal = 'collection_reversal';
    case HandoverIn = 'handover_in';
    case HandoverOut = 'handover_out';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'প্রারম্ভিক ক্যাশ',
            self::Collection => 'সদস্যের কাছ থেকে সংগ্রহ',
            self::CollectionReversal => 'সংগ্রহ বাতিল',
            self::HandoverIn => 'হস্তান্তর গ্রহণ',
            self::HandoverOut => 'হস্তান্তর প্রদান',
            self::Adjustment => 'ক্যাশ সমন্বয়',
        };
    }
}
