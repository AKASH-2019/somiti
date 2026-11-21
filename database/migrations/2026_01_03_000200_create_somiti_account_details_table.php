<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Current share position of a Somiti account.
 * share_value is the value at opening and never changes for this account (historical accuracy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('somiti_account_details', function (Blueprint $table) {
            $table->foreignId('account_id')->primary()->constrained('accounts')->restrictOnDelete();
            $table->unsignedInteger('number_of_shares');
            $table->decimal('share_value', 14, 2);
            $table->decimal('daily_deposit_amount', 14, 2);
            $table->date('start_date');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE somiti_account_details
                ADD CONSTRAINT chk_somiti_shares CHECK (number_of_shares >= 1),
                ADD CONSTRAINT chk_somiti_daily CHECK (daily_deposit_amount = number_of_shares * share_value)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('somiti_account_details');
    }
};
