<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Display-only formatting for Bengali UI.
 * Never feed the output of these methods back into calculations.
 */
final class BanglaFormatter
{
    private const DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    public const MONTHS = [
        1 => 'জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন',
        'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর',
    ];

    /** Index = PHP/Carbon day of week (0 = Sunday). */
    public const DAYS = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];

    private const WORDS = [
        'শূন্য', 'এক', 'দুই', 'তিন', 'চার', 'পাঁচ', 'ছয়', 'সাত', 'আট', 'নয়',
        'দশ', 'এগারো', 'বারো', 'তেরো', 'চৌদ্দ', 'পনেরো', 'ষোলো', 'সতেরো', 'আঠারো', 'উনিশ',
        'বিশ', 'একুশ', 'বাইশ', 'তেইশ', 'চব্বিশ', 'পঁচিশ', 'ছাব্বিশ', 'সাতাশ', 'আটাশ', 'ঊনত্রিশ',
        'ত্রিশ', 'একত্রিশ', 'বত্রিশ', 'তেত্রিশ', 'চৌত্রিশ', 'পঁয়ত্রিশ', 'ছত্রিশ', 'সাঁইত্রিশ', 'আটত্রিশ', 'ঊনচল্লিশ',
        'চল্লিশ', 'একচল্লিশ', 'বিয়াল্লিশ', 'তেতাল্লিশ', 'চুয়াল্লিশ', 'পঁয়তাল্লিশ', 'ছেচল্লিশ', 'সাতচল্লিশ', 'আটচল্লিশ', 'ঊনপঞ্চাশ',
        'পঞ্চাশ', 'একান্ন', 'বায়ান্ন', 'তিপ্পান্ন', 'চুয়ান্ন', 'পঞ্চান্ন', 'ছাপ্পান্ন', 'সাতান্ন', 'আটান্ন', 'ঊনষাট',
        'ষাট', 'একষট্টি', 'বাষট্টি', 'তেষট্টি', 'চৌষট্টি', 'পঁয়ষট্টি', 'ছেষট্টি', 'সাতষট্টি', 'আটষট্টি', 'ঊনসত্তর',
        'সত্তর', 'একাত্তর', 'বাহাত্তর', 'তিয়াত্তর', 'চুয়াত্তর', 'পঁচাত্তর', 'ছিয়াত্তর', 'সাতাত্তর', 'আটাত্তর', 'ঊনআশি',
        'আশি', 'একাশি', 'বিরাশি', 'তিরাশি', 'চুরাশি', 'পঁচাশি', 'ছিয়াশি', 'সাতাশি', 'অষ্টাশি', 'ঊননব্বই',
        'নব্বই', 'একানব্বই', 'বিরানব্বই', 'তিরানব্বই', 'চুরানব্বই', 'পঁচানব্বই', 'ছিয়ানব্বই', 'সাতানব্বই', 'আটানব্বই', 'নিরানব্বই',
    ];

    /** Converts Bengali digits typed by users (০১৭…) to ASCII before validation or search. */
    public static function toAsciiDigits(?string $value): string
    {
        return str_replace(self::DIGITS, range(0, 9), (string) $value);
    }

    public static function digits(int|float|string|null $value): string
    {
        return str_replace(range(0, 9), self::DIGITS, (string) $value);
    }

    /** ৳১,২০,০০০ — Bangladeshi grouping; paisa shown only when non-zero (or $forceDecimals). */
    public static function money(int|float|string|null $amount, bool $symbol = true, bool $forceDecimals = false): string
    {
        $amount = round((float) $amount, 2);
        $negative = $amount < 0;
        [$int, $dec] = explode('.', number_format(abs($amount), 2, '.', ''));

        $grouped = strlen($int) > 3
            ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3)) . ',' . substr($int, -3)
            : $int;

        $text = $grouped . (($dec !== '00' || $forceDecimals) ? '.' . $dec : '');

        return ($negative ? '-' : '') . ($symbol ? '৳' : '') . self::digits($text);
    }

    public static function date(DateTimeInterface|string|null $date, bool $withDay = false): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $d = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date);
        $text = self::digits($d->format('j')) . ' ' . self::MONTHS[(int) $d->format('n')] . ' ' . self::digits($d->format('Y'));

        return $withDay ? self::DAYS[(int) $d->format('w')] . ', ' . $text : $text;
    }

    public static function dateTime(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $d = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date);
        $hour = (int) $d->format('G');
        $period = match (true) {
            $hour < 6 => 'রাত',
            $hour < 12 => 'সকাল',
            $hour < 15 => 'দুপুর',
            $hour < 18 => 'বিকাল',
            default => 'রাত',
        };
        if ($hour >= 18 && $hour < 20) {
            $period = 'সন্ধ্যা';
        }

        return self::date($d) . ', ' . $period . ' ' . self::digits($d->format('g:i'));
    }

    /** "এক লক্ষ তেইশ হাজার চারশত ছাপ্পান্ন টাকা মাত্র" */
    public static function amountInWords(int|float|string $amount): string
    {
        $amount = round(abs((float) $amount), 2);
        $taka = (int) floor($amount);
        $paisa = (int) round(($amount - $taka) * 100);

        $text = self::numberToWords($taka) . ' টাকা';
        if ($paisa > 0) {
            $text .= ' ' . self::numberToWords($paisa) . ' পয়সা';
        }

        return $text . ' মাত্র';
    }

    public static function numberToWords(int $n): string
    {
        if ($n < 100) {
            return self::WORDS[$n];
        }

        $parts = [];
        $crore = intdiv($n, 10000000);
        $n %= 10000000;
        if ($crore > 0) {
            $parts[] = self::numberToWords($crore) . ' কোটি';
        }
        foreach ([[100000, 'লক্ষ'], [1000, 'হাজার']] as [$unit, $name]) {
            $q = intdiv($n, $unit);
            $n %= $unit;
            if ($q > 0) {
                $parts[] = self::WORDS[$q] . ' ' . $name;
            }
        }
        $hundreds = intdiv($n, 100);
        $n %= 100;
        if ($hundreds > 0) {
            $parts[] = self::WORDS[$hundreds] . 'শত';
        }
        if ($n > 0) {
            $parts[] = self::WORDS[$n];
        }

        return implode(' ', $parts);
    }
}
