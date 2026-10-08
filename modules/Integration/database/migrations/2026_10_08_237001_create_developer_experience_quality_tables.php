<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_dev_sandboxes', function (Blueprint $table) {
            $table->id();
            $table->string('sandbox_code')->unique();
            $table->string('developer_id')->index();
            $table->string('environment_type'); // SANDBOX, STAGING, EPHEMERAL
            $table->string('domain_slice'); // HEALTHCARE, MINING, FINTECH, ALL_30_LINES
            $table->string('status')->default('ACTIVE'); // ACTIVE, STALE, RECLAIMED
            $table->timestamp('last_activity_at');
            $table->timestamps();
        });

        Schema::create('platform_ci_quality_gates', function (Blueprint $table) {
            $table->id();
            $table->string('gate_run_code')->unique();
            $table->string('commit_hash');
            $table->boolean('lint_passed');
            $table->boolean('static_analysis_passed');
            $table->boolean('security_audit_passed');
            $table->decimal('mutation_score_pct', 5, 2);
            $table->string('gate_verdict'); // PASSED, REJECTED
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_synthetic_data_seeds', function (Blueprint $table) {
            $table->id();
            $table->string('seed_code')->unique();
            $table->string('business_line')->index();
            $table->string('record_type');
            $table->boolean('is_pii_masked')->default(true); // 237.3 & 237.7
            $table->string('deterministic_seed_key');
            $table->json('masked_sample_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_synthetic_data_seeds');
        Schema::dropIfExists('platform_ci_quality_gates');
        Schema::dropIfExists('platform_dev_sandboxes');
    }
};
