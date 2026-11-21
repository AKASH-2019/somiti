<?php

namespace App\Reports;

use App\Support\BanglaFormatter;
use DateTimeInterface;

final class ReportFormatter
{
    /** Bengali display for screen, print and PDF. */
    public static function display(mixed $value, Column $c): string
    {
        if ($value === null || $value === '') {
            return $c->isNumeric() ? BanglaFormatter::digits(0) : '—';
        }

        return match ($c->type) {
            'money' => BanglaFormatter::money($value),
            'int' => BanglaFormatter::digits((int) $value),
            'date' => BanglaFormatter::date($value),
            'datetime' => BanglaFormatter::dateTime($value),
            default => (string) $value,
        };
    }

    /** Excel keeps numbers as numbers so they can be summed; dates stay readable Bengali text. */
    public static function excel(mixed $value, Column $c): mixed
    {
        return match ($c->type) {
            'money' => round((float) $value, 2),
            'int' => (int) $value,
            'date' => $value ? BanglaFormatter::date($value instanceof DateTimeInterface ? $value : (string) $value) : '',
            'datetime' => $value ? BanglaFormatter::dateTime($value instanceof DateTimeInterface ? $value : (string) $value) : '',
            default => (string) ($value ?? ''),
        };
    }
}
