<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * প্রারম্ভিক সঞ্চয়: savings a member already had on paper before this account started in the system.
 * It counts towards the member's total savings but is NOT cash in any collector's hand,
 * so it never creates a transaction or a cash-ledger entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('somiti_account_details', function (Blueprint $table) {
            $table->decimal('opening_balance', 14, 2)->default(0)->after('start_date');
            $table->string('opening_balance_note', 255)->nullable()->after('opening_balance');
            $table->foreignId('opening_balance_by')->nullable()->after('opening_balance_note')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('somiti_account_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opening_balance_by');
            $table->dropColumn(['opening_balance', 'opening_balance_note']);
        });
    }
};
