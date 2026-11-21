<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only cash ledger per cash holder (collector or admin).
 * Convention: credit = cash INTO the holder's hand, debit = cash OUT of it.
 * Balance = SUM(credit) - SUM(debit). Exactly one of debit/credit is > 0 on each row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_cash_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->string('transaction_type', 30); // App\Enums\LedgerType
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->dateTime('transaction_datetime');
            $table->string('description', 255)->nullable();
            $table->uuid('entry_group')->nullable(); // links the two legs of a handover (Phase 6)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['collector_id', 'transaction_datetime']);
            $table->index(['reference_type', 'reference_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE collector_cash_ledger ADD CONSTRAINT chk_ledger_one_side
                CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_cash_ledger');
    }
};
