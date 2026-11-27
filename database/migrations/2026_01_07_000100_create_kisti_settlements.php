<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kisti settlement: close a loan early by accepting a smaller one-time amount.
 *  - The amount received is a normal collection (receipt + cash ledger).
 *  - The rest is waived (মওকুফ): each open installment's amount_due is reduced to what was paid,
 *    and the reduction is kept in installments.amount_waived so it can be shown and undone.
 *
 * Safe to re-run: every step checks whether it has already been done.
 * Works on MySQL 8 (Aiven) and MariaDB (local): both understand DROP CONSTRAINT.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('installments', 'amount_waived')) {
            Schema::table('installments', function (Blueprint $table) {
                $table->decimal('amount_waived', 14, 2)->default(0)->after('amount_paid');
            });
        }

        // Waived installments can end with amount_due = 0, so allow 0 (was > 0).
        if (DB::getDriverName() === 'mysql') {
            if ($this->checkExists('installments', 'chk_installment_amounts')) {
                DB::statement('ALTER TABLE installments DROP CONSTRAINT chk_installment_amounts');
            }
            if (! $this->checkExists('installments', 'chk_installment_amounts_v2')) {
                DB::statement('ALTER TABLE installments ADD CONSTRAINT chk_installment_amounts_v2
                    CHECK (amount_due >= 0 AND amount_paid >= 0 AND amount_waived >= 0 AND amount_paid <= amount_due)');
            }
        }

        if (! Schema::hasTable('kisti_settlements')) {
            Schema::create('kisti_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
                $table->foreignId('transaction_id')->constrained('transactions')->restrictOnDelete();
                $table->decimal('outstanding_before', 14, 2);
                $table->decimal('settled_amount', 14, 2);
                $table->decimal('waived_amount', 14, 2);
                $table->string('reason', 255);
                $table->foreignId('receiver_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('settled_by')->constrained('users')->restrictOnDelete();
                $table->string('status', 20)->default('active'); // active | reversed
                $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('reversed_at')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kisti_settlements');
        if (DB::getDriverName() === 'mysql' && $this->checkExists('installments', 'chk_installment_amounts_v2')) {
            DB::statement('ALTER TABLE installments DROP CONSTRAINT chk_installment_amounts_v2');
        }
        if (Schema::hasColumn('installments', 'amount_waived')) {
            Schema::table('installments', fn (Blueprint $table) => $table->dropColumn('amount_waived'));
        }
    }

    private function checkExists(string $table, string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->exists();
    }
};
