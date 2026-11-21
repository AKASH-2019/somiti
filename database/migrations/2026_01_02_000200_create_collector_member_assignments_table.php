<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** assigned_to = NULL means the assignment is current. At most one open row per member (enforced in service). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_member_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_from');
            $table->dateTime('assigned_to')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['member_id', 'assigned_to']);
            $table->index(['collector_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_member_assignments');
    }
};
