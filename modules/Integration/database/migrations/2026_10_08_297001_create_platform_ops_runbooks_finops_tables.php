<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_automated_runbooks', function (Blueprint $table) {
            $table->id();
            $table->string('runbook_code')->unique();
            $table->string('task_type'); // REPLAY_DLQ, RESTORE_CACHE, FLUSH_REPORTS
            $table->boolean('dry_run_verified')->default(false); // 297.1 & 297.5
            $table->boolean('is_material_action')->default(true);
            $table->string('approver_lead_id')->nullable();
            $table->boolean('is_executed')->default(false);
            $table->string('execution_evidence_log')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_scheduled_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_code')->unique();
            $table->string('owner_lead_id');
            $table->string('cadence'); // HOURLY, DAILY
            $table->boolean('is_currently_running')->default(false); // 297.2 & 297.5 Overlap guard
            $table->integer('consecutive_failure_count')->default(0); // 297.7
            $table->boolean('owner_alert_sent')->default(false); // 297.7
            $table->timestamps();
        });

        Schema::create('platform_operational_readiness_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_code')->unique();
            $table->string('feature_name');
            $table->boolean('has_on_call_roster')->default(false);
            $table->boolean('has_grafana_dashboard')->default(false);
            $table->boolean('has_rollback_plan')->default(false);
            $table->boolean('has_cost_estimate')->default(false);
            $table->boolean('is_release_authorized')->default(false); // 297.4, 297.5, 297.8
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_operational_readiness_reviews');
        Schema::dropIfExists('platform_scheduled_jobs');
        Schema::dropIfExists('platform_automated_runbooks');
    }
};
