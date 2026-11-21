<?php

namespace Tests\Unit;

use App\Support\BanglaFormatter as F;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BanglaFormatterTest extends TestCase
{
    public static function moneyCases(): array
    {
        return [
            [20, '৳২০'], [500, '৳৫০০'], [24000, '৳২৪,০০০'], [120000, '৳১,২০,০০০'],
            [12345678, '৳১,২৩,৪৫,৬৭৮'], [1234.5, '৳১,২৩৪.৫০'], [-2000, '-৳২,০০০'],
        ];
    }

    #[DataProvider('moneyCases')]
    public function test_money_uses_bangladeshi_grouping(float|int $amount, string $expected): void
    {
        $this->assertSame($expected, F::money($amount));
    }

    public static function wordCases(): array
    {
        return [
            [0, 'শূন্য টাকা মাত্র'],
            [500, 'পাঁচশত টাকা মাত্র'],
            [10000, 'দশ হাজার টাকা মাত্র'],
            [24000, 'চব্বিশ হাজার টাকা মাত্র'],
            [123456, 'এক লক্ষ তেইশ হাজার চারশত ছাপ্পান্ন টাকা মাত্র'],
            [100.5, 'একশত টাকা পঞ্চাশ পয়সা মাত্র'],
            [250000000, 'পঁচিশ কোটি টাকা মাত্র'],
        ];
    }

    #[DataProvider('wordCases')]
    public function test_amount_in_words(float|int $amount, string $expected): void
    {
        $this->assertSame($expected, F::amountInWords($amount));
    }

    public function test_digits_and_dates(): void
    {
        $this->assertSame('২০২৬', F::digits(2026));
        $this->assertSame('বৃহস্পতিবার, ১৯ নভেম্বর ২০২৬', F::date('2026-11-19', true));
    }
}
