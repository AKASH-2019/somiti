<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collectors created before Phase 6 have opening_cash on their user row but no ledger entry yet.
 * Post one "opening" credit for each, once. New collectors get it at creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')->where('opening_cash', '>', 0)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('collector_cash_ledger as l')
                ->whereColumn('l.collector_id', 'users.id')->where('l.transaction_type', 'opening'))
            ->get(['id', 'opening_cash', 'created_at']);

        foreach ($users as $u) {
            DB::table('collector_cash_ledger')->insert([
                'collector_id' => $u->id,
                'transaction_type' => 'opening',
                'reference_type' => null,
                'reference_id' => null,
                'debit' => 0,
                'credit' => $u->opening_cash,
                'transaction_datetime' => $u->created_at ?? now(),
                'description' => 'প্রারম্ভিক ক্যাশ',
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Ledger is append-only; nothing to undo.
    }
};
