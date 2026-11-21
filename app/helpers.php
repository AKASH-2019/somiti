<?php

use App\Services\SettingService;
use App\Support\BanglaFormatter;

if (! function_exists('bn_digits')) {
    function bn_digits(int|float|string|null $value): string
    {
        return BanglaFormatter::digits($value);
    }
}

if (! function_exists('bn_money')) {
    function bn_money(int|float|string|null $amount, bool $symbol = true): string
    {
        return BanglaFormatter::money($amount, $symbol);
    }
}

if (! function_exists('bn_date')) {
    function bn_date(DateTimeInterface|string|null $date, bool $withDay = false): string
    {
        return BanglaFormatter::date($date, $withDay);
    }
}

if (! function_exists('bn_datetime')) {
    function bn_datetime(DateTimeInterface|string|null $date): string
    {
        return BanglaFormatter::dateTime($date);
    }
}

if (! function_exists('bn_words')) {
    function bn_words(int|float|string $amount): string
    {
        return BanglaFormatter::amountInWords($amount);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingService::class)->get($key, $default);
    }
}
