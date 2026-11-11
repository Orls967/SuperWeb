<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_retention_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('module_name', 48);
            $table->string('source_table', 64)->unique();
            $table->string('archive_table', 80);
            $table->unsignedInteger('retention_days')->default(1825);
            $table->string('native_partition_hint', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('core_retention_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retention_policy_id')->constrained('core_retention_policies')->cascadeOnDelete();
            $table->string('source_table', 64);
            $table->string('archive_table', 80);
            $table->string('chunk_start_ref', 64);
            $table->string('chunk_end_ref', 64);
            $table->unsignedInteger('rows_archived');
            $table->string('chunk_sha256', 64);
            $table->string('status', 24)->default('verified');
            $table->timestamp('executed_at');
            $table->timestamps();
        });

        Schema::create('core_audit_logs_archive', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('archive_run_id')->constrained('core_retention_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('user_ref')->nullable();
            $table->string('action', 100)->index();
            $table->string('auditable_type', 160)->nullable();
            $table->string('auditable_ref', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('archived_at');
        });

        Schema::create('core_archive_drill_restores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retention_run_id')->constrained('core_retention_runs')->cascadeOnDelete();
            $table->string('source_table', 64);
            $table->unsignedInteger('restored_rows');
            $table->string('expected_sha256', 64);
            $table->string('actual_sha256', 64);
            $table->boolean('matched');
            $table->longText('restored_payload');
            $table->timestamp('drilled_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_archive_drill_restores');
        Schema::dropIfExists('core_audit_logs_archive');
        Schema::dropIfExists('core_retention_runs');
        Schema::dropIfExists('core_retention_policies');
    }
};
