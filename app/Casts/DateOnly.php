<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * For DATE columns. Always stores "Y-m-d" (Laravel's built-in "date" cast stores "Y-m-d 00:00:00",
 * which SQLite keeps as text, so unique checks and comparisons against "Y-m-d" fail there).
 * Reads back as a Carbon at the start of the day, exactly like the built-in cast.
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null || $value === '' ? null : Carbon::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : Carbon::parse($value)->toDateString();
    }
}
