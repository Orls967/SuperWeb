<?php

declare(strict_types=1);

namespace Modules\Core\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 143.1 AI Decision Engine Snapshots & Deterministic Audit
        Schema::create('core_ai_decision_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('decision_code')->unique();
            $table->string('model_name'); // e.g. CROSS_LINE_DYNAMIC_PRICING, DISPATCH_OPTIMIZER
            $table->string('model_version');
            $table->string('seed');
            $table->json('input_snapshot');
            $table->json('decision_output');
            $table->string('decision_hash'); // SHA-256 hash of (seed + inputs + output)
            $table->timestamps();

            $table->index(['model_name', 'model_version']);
        });

        // 143.2 Autonomous operations ladder & Kill-switch
        Schema::create('core_autonomous_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('operation_code')->unique();
            $table->string('line_code'); // LINE_1 .. LINE_17
            $table->integer('autonomy_level')->default(1); // 1 = recommendation, 2 = auto-below-threshold, 3 = auto-with-rollback, 4 = full autonomous
            $table->string('action_name');
            $table->boolean('kill_switch_active')->default(false);
            $table->timestamp('kill_switched_at')->nullable();
            $table->string('execution_status')->default('EXECUTED'); // EXECUTED, BLOCKED_BY_KILL_SWITCH
            $table->timestamps();

            $table->index(['line_code', 'autonomy_level']);
        });

        // 143.5 AI Governance Model Drift Check Logs
        Schema::create('core_ai_governance_drift_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('log_code')->unique();
            $table->string('model_name');
            $table->double('data_drift_score', 5, 4); // 0.0000 - 1.0000 (Wasserstein/PSI)
            $table->double('concept_drift_score', 5, 4);
            $table->boolean('requires_retraining')->default(false);
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['model_name', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_ai_governance_drift_logs');
        Schema::dropIfExists('core_autonomous_operations');
        Schema::dropIfExists('core_ai_decision_snapshots');
    }
};
