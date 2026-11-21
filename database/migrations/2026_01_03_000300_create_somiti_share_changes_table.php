<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every share position an account has had, including the opening one (old_shares = NULL).
 * The daily amount for any date = new_daily_amount of the latest row with effective_date <= that date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('somiti_share_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedInteger('old_shares')->nullable();
            $table->unsignedInteger('new_shares');
            $table->decimal('share_value', 14, 2);
            $table->decimal('old_daily_amount', 14, 2)->nullable();
            $table->decimal('new_daily_amount', 14, 2);
            $table->date('effective_date');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['account_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('somiti_share_changes');
    }
};
