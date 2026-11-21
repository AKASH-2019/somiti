<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Race-safe sequential reference numbers (RCP-2026-000123, HO-2026-000045, SM-00012 …).
 * Safe to call inside an outer DB transaction: the row lock is held until that transaction commits,
 * so a rolled-back collection never "consumes" a number another request already saw.
 */
class NumberSequenceService
{
    public function next(string $name, string $prefix, int $pad = 6, bool $yearly = true): string
    {
        $year = $yearly ? (int) now()->format('Y') : 0;

        $value = DB::transaction(function () use ($name, $year) {
            DB::table('number_sequences')->insertOrIgnore([
                'name' => $name, 'year' => $year, 'last_value' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $row = DB::table('number_sequences')
                ->where('name', $name)->where('year', $year)
                ->lockForUpdate()->first();

            $next = $row->last_value + 1;
            DB::table('number_sequences')->where('id', $row->id)
                ->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        });

        $number = str_pad((string) $value, $pad, '0', STR_PAD_LEFT);

        return $yearly ? "{$prefix}-{$year}-{$number}" : "{$prefix}-{$number}";
    }
}
