<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per collection (money received). Never deleted or edited: a wrong one is reversed
 * (status = reversed) and the reversal is recorded in the allocations, installments and cash ledger.
 * collected_at = when money was received; allocations say which days/installments it covers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete(); // who received the money
            $table->string('entry_type', 20)->default('cash');      // App\Enums\EntryType
            $table->string('payment_method', 20)->default('cash');  // App\Enums\PaymentMethod
            $table->decimal('amount', 14, 2);
            $table->dateTime('collected_at');
            $table->string('status', 20)->default('completed');     // App\Enums\TransactionStatus
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reversed_at')->nullable();
            $table->string('reversal_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['collector_id', 'collected_at']);
            $table->index(['account_id', 'collected_at']);
            $table->index(['status', 'collected_at']);
        });

        Schema::create('transaction_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->foreignId('installment_id')->constrained('installments')->restrictOnDelete();
            $table->date('covered_date');
            $table->decimal('amount', 14, 2);
            $table->string('timing', 10);                 // App\Enums\AllocationTiming
            $table->string('status', 10)->default('active'); // active | reversed
            $table->timestamps();

            $table->index(['installment_id', 'status']);
            $table->index('covered_date');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_tx_amount CHECK (amount > 0)');
            DB::statement('ALTER TABLE transaction_allocations ADD CONSTRAINT chk_alloc_amount CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_allocations');
        Schema::dropIfExists('transactions');
    }
};
