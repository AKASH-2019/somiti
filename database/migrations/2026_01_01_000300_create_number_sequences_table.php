<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);          // receipt, handover, member, somiti_account, kisti_account
            $table->unsignedSmallInteger('year'); // 0 for sequences that never reset
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['name', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
