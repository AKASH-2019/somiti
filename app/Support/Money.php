<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact money arithmetic in integer paisa. Never use float math on stored amounts.
 * Inputs are DECIMAL strings from the database ("100.00") or ints.
 */
final class Money
{
    public static function toPaisa(string|int $amount): int
    {
        if (is_int($amount)) {
            return $amount * 100;
        }
        $amount = trim($amount);
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $amount, $m)) {
            throw new InvalidArgumentException("Invalid money value: {$amount}");
        }
        $paisa = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return ($m[1] ?? '') === '-' ? -$paisa : $paisa;
    }

    public static function fromPaisa(int $paisa): string
    {
        $sign = $paisa < 0 ? '-' : '';
        $paisa = abs($paisa);

        return $sign.intdiv($paisa, 100).'.'.str_pad((string) ($paisa % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function multiply(string|int $amount, int $times): string
    {
        return self::fromPaisa(self::toPaisa($amount) * $times);
    }

    public static function add(string|int ...$amounts): string
    {
        return self::fromPaisa(array_sum(array_map(self::toPaisa(...), $amounts)));
    }

    public static function sub(string|int $a, string|int $b): string
    {
        return self::fromPaisa(self::toPaisa($a) - self::toPaisa($b));
    }

    public static function compare(string|int $a, string|int $b): int
    {
        return self::toPaisa($a) <=> self::toPaisa($b);
    }
}
