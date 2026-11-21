<?php

namespace App\Http\Requests\Concerns;

use App\Support\BanglaFormatter;

/**
 * Users often type numbers with Bengali digits (০১৭১…, ১২,৫০০). Clean them before validation.
 * Phones and amounts are cleaned differently: a minus sign means something in an amount.
 */
trait NormalizesBanglaInput
{
    /** "০১৭১১-২২৩৩৪৪", "+880 1711 223344" → "01711223344" */
    protected function normalizePhones(array $fields): void
    {
        $this->cleanFields($fields, function (string $value): string {
            $value = preg_replace('/[\s\-().]/', '', BanglaFormatter::toAsciiDigits($value));
            if (preg_match('/^\+?880(1\d{9})$/', $value, $m)) {
                $value = '0'.$m[1];
            }

            return $value;
        });
    }

    /** "১২,৫০০" → "12500", "-১০" → "-10" (sign kept so validation can reject it). */
    protected function normalizeNumbers(array $fields): void
    {
        $this->cleanFields($fields, fn (string $value): string => preg_replace('/[\s,]/', '', BanglaFormatter::toAsciiDigits($value)));
    }

    private function cleanFields(array $fields, callable $clean): void
    {
        $cleaned = [];
        foreach ($fields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $cleaned[$field] = $clean($this->input($field));
            }
        }
        $this->merge($cleaned);
    }
}
