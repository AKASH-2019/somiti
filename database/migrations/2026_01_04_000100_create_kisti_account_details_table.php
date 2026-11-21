<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loan terms. Fixed at creation — never edited (a mistaken loan is cancelled and re-entered).
 * total_installments = ceil(total_payable / installment_amount); the last one carries the remainder.
 * Loan disbursement is outside the collector cash ledger (business rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kisti_account_details', function (Blueprint $table) {
            $table->foreignId('account_id')->primary()->constrained('accounts')->restrictOnDelete();
            $table->decimal('loan_amount', 14, 2);
            $table->decimal('service_charge', 14, 2)->default(0);
            $table->decimal('total_payable', 14, 2);
            $table->string('frequency', 10); // App\Enums\KistiFrequency
            $table->decimal('installment_amount', 14, 2);
            $table->unsignedInteger('total_installments');
            $table->decimal('last_installment_amount', 14, 2);
            $table->date('start_date');             // কিস্তি শুরুর তারিখ (loan given)
            $table->date('first_installment_date'); // প্রথম কিস্তির তারিখ
            $table->timestamps();

            $table->index('frequency');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE kisti_account_details
                ADD CONSTRAINT chk_kisti_total CHECK (total_payable = loan_amount + service_charge),
                ADD CONSTRAINT chk_kisti_amounts CHECK (loan_amount > 0 AND service_charge >= 0 AND installment_amount > 0
                    AND last_installment_amount > 0 AND last_installment_amount <= installment_amount),
                ADD CONSTRAINT chk_kisti_schedule CHECK (
                    installment_amount * (total_installments - 1) + last_installment_amount = total_payable),
                ADD CONSTRAINT chk_kisti_dates CHECK (first_installment_date >= start_date)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kisti_account_details');
    }
};
