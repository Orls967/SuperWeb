<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_release_trains', function (Blueprint $table) {
            $table->id();
            $table->string('train_code')->unique();
            $table->string('release_type'); // SCHEDULED, HOTFIX
            $table->string('status')->default('SCHEDULED'); // SCHEDULED, IN_PROGRESS, COMPLETED, CANCELLED
            $table->timestamp('scheduled_at');
            $table->text('release_notes_summary')->nullable();
            $table->string('approved_by')->nullable();
            $table->boolean('post_merge_review_completed')->default(false); // 238.6
            $table->timestamps();
        });

        Schema::create('platform_change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('cr_code')->unique();
            $table->string('title');
            $table->string('blast_radius_type'); // MONEY, PII, AVAILABILITY, LOW_RISK
            $table->decimal('risk_score', 5, 2);
            $table->string('cab_approval_status')->default('NOT_REQUIRED'); // NOT_REQUIRED, PENDING, APPROVED, REJECTED
            $table->boolean('rollback_plan_documented')->default(false);
            $table->boolean('post_deploy_verified')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_schema_migrations_safety', function (Blueprint $table) {
            $table->id();
            $table->string('migration_name');
            $table->string('migration_pattern'); // EXPAND_CONTRACT, STANDARD, DUAL_WRITE
            $table->boolean('is_breaking_change')->default(false);
            $table->boolean('has_lint_passed')->default(true);
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_canary_rollouts', function (Blueprint $table) {
            $table->id();
            $table->string('rollout_code')->unique();
            $table->unsignedBigInteger('release_train_id')->nullable();
            $table->decimal('current_traffic_pct', 5, 2);
            $table->decimal('error_rate_pct', 5, 2);
            $table->string('status')->default('IN_PROGRESS'); // IN_PROGRESS, PROMOTED, AUTO_ROLLED_BACK
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_canary_rollouts');
        Schema::dropIfExists('platform_schema_migrations_safety');
        Schema::dropIfExists('platform_change_requests');
        Schema::dropIfExists('platform_release_trains');
    }
};
