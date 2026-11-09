<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_risk_weights', function (Blueprint $table): void {
            $table->id();
            $table->string('factor_key')->unique();
            $table->integer('weight_bps');
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasTable('plt_release_trains')) {
            Schema::create('plt_release_trains', function (Blueprint $table): void {
                $table->id();
                $table->string('train_code')->unique();
                $table->string('version');
                $table->foreignId('release_manager_id')->constrained('users')->cascadeOnDelete();
                $table->string('status')->default('open');
                $table->timestamp('freeze_at')->nullable();
                $table->timestamp('scheduled_deploy_at')->nullable();
                $table->timestamp('deployed_at')->nullable();
                $table->text('release_notes_markdown')->nullable();
                $table->timestamps();

                $table->index(['status', 'scheduled_deploy_at']);
            });
        } else {
            Schema::table('plt_release_trains', function (Blueprint $table): void {
                if (! Schema::hasColumn('plt_release_trains', 'version')) {
                    $table->string('version')->default('v1.0.0');
                }
                if (! Schema::hasColumn('plt_release_trains', 'release_manager_id')) {
                    $table->foreignId('release_manager_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('plt_release_trains', 'freeze_at')) {
                    $table->timestamp('freeze_at')->nullable();
                }
                if (! Schema::hasColumn('plt_release_trains', 'scheduled_deploy_at')) {
                    $table->timestamp('scheduled_deploy_at')->nullable();
                }
                if (! Schema::hasColumn('plt_release_trains', 'deployed_at')) {
                    $table->timestamp('deployed_at')->nullable();
                }
                if (! Schema::hasColumn('plt_release_trains', 'release_notes_markdown')) {
                    $table->text('release_notes_markdown')->nullable();
                }
            });
        }

        Schema::create('plt_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_module');
            $table->string('change_type')->default('normal');
            $table->string('status')->default('draft');
            $table->integer('risk_score')->default(0);
            $table->string('risk_tier')->default('low');
            $table->foreignId('release_train_id')->nullable()->constrained('plt_release_trains')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'risk_tier']);
        });

        Schema::create('plt_change_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('change_request_id')->constrained('plt_change_requests')->cascadeOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->json('transition_metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('plt_rollback_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('change_request_id')->unique()->constrained('plt_change_requests')->cascadeOnDelete();
            $table->string('strategy');
            $table->integer('estimated_rto_seconds')->default(60);
            $table->boolean('automated')->default(true);
            $table->json('playbook_steps')->nullable();
            $table->json('verification_checklist')->nullable();
            $table->timestamps();
        });

        Schema::create('plt_hotfixes', function (Blueprint $table): void {
            $table->id();
            $table->string('hotfix_number')->unique();
            $table->foreignId('release_train_id')->constrained('plt_release_trains')->cascadeOnDelete();
            $table->foreignId('change_request_id')->nullable()->constrained('plt_change_requests')->nullOnDelete();
            $table->string('severity')->default('p2_critical');
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->string('isolation_status')->default('quarantined');
            $table->timestamps();
        });

        Schema::create('plt_deployments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_train_id')->constrained('plt_release_trains')->cascadeOnDelete();
            $table->string('environment')->default('staging');
            $table->string('strategy')->default('canary');
            $table->integer('traffic_percentage')->default(10);
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plt_canary_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deployment_id')->constrained('plt_deployments')->cascadeOnDelete();
            $table->timestamp('sample_window_start');
            $table->timestamp('sample_window_end');
            $table->integer('baseline_requests');
            $table->integer('baseline_errors');
            $table->integer('canary_requests');
            $table->integer('canary_errors');
            $table->integer('z_score')->default(0);
            $table->integer('relative_risk_bps')->default(10000);
            $table->string('recommendation')->default('continue');
            $table->timestamps();
        });

        Schema::create('plt_backfills', function (Blueprint $table): void {
            $table->id();
            $table->string('job_name')->unique();
            $table->string('target_table');
            $table->integer('total_records')->default(0);
            $table->integer('chunk_size')->default(100);
            $table->string('status')->default('pending');
            $table->integer('cursor_pointer')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('plt_backfill_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('backfill_id')->constrained('plt_backfills')->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->integer('range_start');
            $table->integer('range_end');
            $table->integer('records_processed')->default(0);
            $table->string('status')->default('pending');
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['backfill_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_backfill_chunks');
        Schema::dropIfExists('plt_backfills');
        Schema::dropIfExists('plt_canary_observations');
        Schema::dropIfExists('plt_deployments');
        Schema::dropIfExists('plt_hotfixes');
        Schema::dropIfExists('plt_rollback_plans');
        Schema::dropIfExists('plt_change_transitions');
        Schema::dropIfExists('plt_change_requests');
        if (Schema::hasTable('plt_release_trains') && ! Schema::hasColumn('plt_release_trains', 'departure_date')) {
            Schema::dropIfExists('plt_release_trains');
        }
        Schema::dropIfExists('plt_risk_weights');
    }
};
