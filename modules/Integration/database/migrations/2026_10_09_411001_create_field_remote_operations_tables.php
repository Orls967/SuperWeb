<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_field_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_code')->unique();
            $table->string('site_name');
            $table->string('operator_id');
            $table->boolean('credentials_verified')->default(false); // 411.1
            $table->boolean('safety_permit_issued')->default(false); // 411.1
            $table->boolean('equipment_checked')->default(false); // 411.1
            $table->boolean('pre_start_cleared')->default(false); // 411.1, 411.4
            $table->string('status')->default('pending_pre_start'); // pending_pre_start, in_progress, completed, halted_safety
            $table->integer('hours_offline')->default(0);
            $table->boolean('connectivity_safe_halt')->default(false); // 411.5 edge case
            $table->string('post_op_evidence_hash')->nullable(); // 411.3
            $table->string('reviewer')->nullable();
            $table->boolean('records_sealed')->default(false);
            $table->timestamps();
        });

        Schema::create('ops_field_offline_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_idempotency_key')->unique(); // 411.2, 411.4
            $table->foreignId('task_id')->constrained('ops_field_tasks')->cascadeOnDelete();
            $table->json('payload');
            $table->boolean('synced')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_field_offline_syncs');
        Schema::dropIfExists('ops_field_tasks');
    }
};
