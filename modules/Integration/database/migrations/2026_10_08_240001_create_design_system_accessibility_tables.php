<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_design_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_key')->unique();
            $table->string('category'); // COLOR, TYPOGRAPHY, SPACING, SHADOW, RADIUS
            $table->string('light_value');
            $table->string('dark_value')->nullable(); // 240.7
            $table->string('high_contrast_value')->nullable(); // 240.7
            $table->timestamps();
        });

        Schema::create('platform_ui_components', function (Blueprint $table) {
            $table->id();
            $table->string('component_code')->unique();
            $table->string('component_name');
            $table->boolean('is_canonical')->default(true);
            $table->string('requesting_module')->default('CORE');
            $table->boolean('custom_fork_approved')->default(false); // 240.6
            $table->decimal('wcag_contrast_ratio', 4, 2)->default(4.50);
            $table->boolean('keyboard_navigable')->default(true);
            $table->boolean('aria_label_compliant')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_a11y_responsive_scans', function (Blueprint $table) {
            $table->id();
            $table->string('scan_code')->unique();
            $table->string('route_path');
            $table->integer('viewport_width_px'); // e.g. 375 for mobile-first
            $table->integer('wcag_violations_count')->default(0);
            $table->boolean('responsive_overflow_detected')->default(false);
            $table->boolean('is_gate_passed')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_ux_research_backlog', function (Blueprint $table) {
            $table->id();
            $table->string('finding_code')->unique();
            $table->string('module_code');
            $table->decimal('task_success_rate_pct', 5, 2);
            $table->text('usability_issue_description');
            $table->string('remediation_status')->default('OPEN'); // OPEN, IN_PROGRESS, RESOLVED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ux_research_backlog');
        Schema::dropIfExists('platform_a11y_responsive_scans');
        Schema::dropIfExists('platform_ui_components');
        Schema::dropIfExists('platform_design_tokens');
    }
};
