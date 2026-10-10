<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Approval Requests (Header)
        Schema::create('core_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('approvable_type')->nullable();
            $table->string('approvable_id', 64)->nullable(); // id bigint atau UUID/ULID entitas yang disetujui
            $table->index(['approvable_type', 'approvable_id']); // Target entity being approved (Claim, PO, Contract, Asset, etc.)
            $table->string('approval_type', 50)->index(); // CLAIM, PO, CONTRACT, CARRIER_PAYMENT, ASSET_WRITE_OFF
            $table->string('title', 255);
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 10)->default('IDR');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('pending')->index(); // pending, approved, rejected, escalated, cancelled
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->unsignedTinyInteger('total_steps')->default(1);
            $table->timestamp('sla_due_at')->nullable()->index();
            $table->timestamp('decided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 2. Approval Steps (Workflow Definition & State per Level)
        Schema::create('core_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('core_approvals')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_number')->index(); // 1, 2, 3 ...
            $table->string('role_required', 50)->nullable(); // Role permitted to decide this step
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete(); // Specific user if delegated
            $table->string('status', 30)->default('pending'); // pending, approved, rejected, bypassed, delegated
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delegated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['approval_id', 'step_number']);
        });

        // 3. Approval Histori & Audit Trail
        Schema::create('core_approval_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('core_approvals')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_number');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); // SUBMITTED, APPROVED, REJECTED, DELEGATED, ESCALATED, CANCELLED
            $table->text('notes')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_approval_histories');
        Schema::dropIfExists('core_approval_steps');
        Schema::dropIfExists('core_approval_requests');
        Schema::dropIfExists('core_approvals');
    }
};
