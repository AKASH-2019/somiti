<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * members.collector_id is the CURRENT collector (fast filtering);
 * collector_member_assignments keeps the full history. MemberAssignmentService keeps both in sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_code', 20)->unique();
            $table->string('name', 150);
            $table->string('business_name', 150)->nullable();
            $table->string('business_address', 255)->nullable();
            $table->string('mobile', 11);
            $table->date('joined_at');
            $table->string('status', 20)->default('active'); // App\Enums\MemberStatus
            $table->foreignId('collector_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('mobile');
            $table->index('name');
            $table->index(['collector_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
