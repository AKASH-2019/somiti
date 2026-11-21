<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The payment schedule for both modules.
 *  - Somiti: one row per working day, generated up to today (and further when paid in advance).
 *  - Kisti: full schedule generated at loan creation (Phase 4).
 * amount_paid is updated only by the collection engine (Phase 5), inside a locked DB transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedInteger('sequence_no');
            $table->date('due_date');
            $table->decimal('amount_due', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('status', 10)->default('unpaid'); // App\Enums\InstallmentStatus
            $table->timestamps();

            $table->unique(['account_id', 'due_date']);
            $table->unique(['account_id', 'sequence_no']);
            $table->index(['status', 'due_date']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE installments
                ADD CONSTRAINT chk_installment_amounts CHECK (amount_due > 0 AND amount_paid >= 0 AND amount_paid <= amount_due)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
