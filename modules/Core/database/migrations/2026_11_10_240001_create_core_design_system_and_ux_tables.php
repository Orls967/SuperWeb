<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. core_ds_tokens
        Schema::create('core_ds_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('theme', 20); // light, dark, hc
            $table->string('token_name', 100)->nullable();
            $table->string('token_key', 100);
            $table->string('token_type', 50)->default('color');
            $table->string('token_value', 255)->nullable();
            $table->string('raw_value', 255);
            $table->string('resolved_value', 255)->nullable();
            $table->boolean('is_semantic')->default(true);
            $table->string('description', 255)->nullable();
            $table->integer('version')->default(1);
            $table->string('label', 50)->default('asumsi_simulasi');
            $table->timestamps();

            $table->unique(['theme', 'token_key', 'version'], 'core_ds_tokens_unique');
            $table->index('theme');
        });

        // 2. core_ds_components
        Schema::create('core_ds_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('component_name', 100)->nullable();
            $table->string('display_name', 128)->nullable();
            $table->string('category', 50)->default('general');
            $table->string('status', 50)->default('draft');
            $table->string('current_version', 32)->default('1.0.0');
            $table->string('wcag_level', 10)->default('AA');
            $table->string('spec_doc_url', 255)->nullable();
            $table->text('notes')->nullable();
            $table->json('props_schema')->nullable();
            $table->json('slots_schema')->nullable();
            $table->json('events_schema')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->string('requested_by_module', 50)->default('CORE');
            $table->text('reason')->nullable();
            $table->string('replacement_component', 100)->nullable();
            $table->string('documentation_url', 255)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('category');
        });

        // 3. core_ds_component_history
        Schema::create('core_ds_component_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('core_ds_components')->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 50)->default('system');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('transitioned_at')->nullable();
            $table->timestamps();

            $table->index(['component_id', 'created_at']);
        });

        // 4. core_ds_a11y_findings
        Schema::create('core_ds_a11y_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('core_ds_components')->cascadeOnDelete();
            $table->string('rule_violated', 100);
            $table->string('severity', 20)->default('medium'); // low, medium, high, critical
            $table->text('description');
            $table->boolean('is_remediated')->default(false);
            $table->timestamp('remediated_at')->nullable();
            $table->timestamps();

            $table->index(['component_id', 'is_remediated']);
        });

        // 5. core_ds_component_usage
        Schema::create('core_ds_component_usage', function (Blueprint $table) {
            $table->id();
            $table->string('component_name', 100);
            $table->string('view_path', 255);
            $table->string('module', 50)->default('Core');
            $table->integer('usage_count')->default(1);
            $table->boolean('has_deprecated_props')->default(false);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamp('last_detected_at')->nullable();
            $table->timestamps();

            $table->unique(['component_name', 'view_path'], 'core_ds_usage_unique');
            $table->index('component_name');
        });

        // 6. core_ds_user_prefs
        Schema::create('core_ds_user_prefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->unique();
            $table->string('theme', 20)->default('light');
            $table->string('density', 30)->default('comfortable');
            $table->integer('font_scale')->default(100);
            $table->boolean('reduced_motion')->default(false);
            $table->boolean('high_contrast')->default(false);
            $table->timestamps();
        });

        // 7. core_ux_iterations
        Schema::create('core_ux_iterations', function (Blueprint $table) {
            $table->id();
            $table->integer('iteration_number')->default(1)->unique();
            $table->string('name', 100)->nullable();
            $table->string('iteration_name', 100)->nullable();
            $table->decimal('sus_score', 5, 2)->default(75.00);
            $table->decimal('task_success_rate', 5, 2)->default(85.00);
            $table->integer('participants_count')->default(10);
            $table->string('methodology', 100)->default('moderated');
            $table->text('summary')->nullable();
            $table->integer('task_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('task_success_rate_bps')->default(0);
            $table->timestamp('tested_at')->nullable();
            $table->timestamp('conducted_at')->useCurrent();
            $table->timestamps();

            $table->index('conducted_at');
        });

        // 8. core_ux_findings
        Schema::create('core_ux_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iteration_id')->nullable()->constrained('core_ux_iterations')->cascadeOnDelete();
            $table->foreignId('component_id')->nullable()->constrained('core_ds_components')->nullOnDelete();
            $table->string('finding_code', 50)->nullable();
            $table->string('module_code', 50)->default('CORE');
            $table->string('category', 50)->default('usability');
            $table->string('severity', 20)->default('medium'); // blocker, major, minor, suggestion
            $table->text('description');
            $table->text('recommendation')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->string('status', 50)->default('OPEN'); // OPEN, BACKLOG, REMEDIATED
            $table->timestamps();

            $table->index('status');
            $table->index('module_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_ux_findings');
        Schema::dropIfExists('core_ux_iterations');
        Schema::dropIfExists('core_ds_user_prefs');
        Schema::dropIfExists('core_ds_component_usage');
        Schema::dropIfExists('core_ds_a11y_findings');
        Schema::dropIfExists('core_ds_component_history');
        Schema::dropIfExists('core_ds_components');
        Schema::dropIfExists('core_ds_tokens');
    }
};
