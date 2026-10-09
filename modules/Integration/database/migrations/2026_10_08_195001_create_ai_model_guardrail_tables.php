<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 195.1: Centralized AI model registry with versioning, training data hash, and rollback pointer
        Schema::create('ai_model_registry', function (Blueprint $table) {
            $table->id();
            $table->string('model_code')->unique();
            $table->string('domain_code'); // e.g. L07_INSURANCE, L11_HEALTHCARE
            $table->string('version'); // 1.0.0, 1.1.0
            $table->string('training_data_hash'); // SHA-256 snapshot
            $table->decimal('eval_score_auc', 6, 4)->default(0.0000);
            $table->string('rollback_version')->nullable();
            $table->boolean('is_approved_for_release')->default(false);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // 195.3: Guardrails input/output validation & prompt injection rejection
        Schema::create('ai_guardrail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_code')->unique();
            $table->string('domain_code');
            $table->text('sanitized_input');
            $table->boolean('injection_detected')->default(false);
            $table->boolean('pii_redacted')->default(true);
            $table->string('status')->default('PASSED'); // PASSED, BLOCKED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_guardrail_logs');
        Schema::dropIfExists('ai_model_registry');
    }
};
