<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collector-to-collector (or to/from admin) cash handovers.
 * Cash moves in the ledger ONLY when the receiver confirms (or the admin resolves a dispute as received).
 * Rows are never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('handover_number', 30)->unique();
            $table->foreignId('sender_collector_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('receiver_collector_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->dateTime('handover_datetime');
            $table->dateTime('received_datetime')->nullable();
            $table->string('status', 20)->default('pending'); // App\Enums\HandoverStatus
            $table->text('notes')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('disputed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disputed_at')->nullable();
            $table->string('dispute_reason', 255)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->string('resolution_note', 255)->nullable();
            $table->timestamps();

            $table->index(['sender_collector_id', 'status']);
            $table->index(['receiver_collector_id', 'status']);
            $table->index(['status', 'handover_datetime']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cash_handovers
                ADD CONSTRAINT chk_handover_amount CHECK (amount > 0),
                ADD CONSTRAINT chk_handover_parties CHECK (sender_collector_id <> receiver_collector_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_handovers');
    }
};
