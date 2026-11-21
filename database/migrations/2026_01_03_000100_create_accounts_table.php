<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per financial account (Somiti savings or Kisti loan). Type-specific data lives in *_account_details. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_no', 20)->unique();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->string('type', 10); // App\Enums\AccountType
            $table->foreignId('assigned_collector_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active'); // App\Enums\AccountStatus
            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['assigned_collector_id', 'type', 'status']);
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
