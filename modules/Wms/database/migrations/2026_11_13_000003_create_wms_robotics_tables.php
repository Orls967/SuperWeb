<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wms_robotics_plans')) {
            Schema::create('wms_robotics_plans', function (Blueprint $table): void {
                $table->id();
                $table->uuid('warehouse_id')->index();
                $table->string('pick_method', 64);
                $table->unsignedInteger('target_lines_per_hour');
                $table->unsignedBigInteger('capex_idr');
                $table->unsignedBigInteger('annual_savings_idr');
                $table->unsignedInteger('roi_bps');
                $table->unsignedInteger('payback_months');
                $table->string('phase_stage', 64);
                $table->string('certificate_hash', 64);
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->index(['warehouse_id', 'phase_stage'], 'wms_robotics_plans_wh_stage_idx');
            });
        }

        if (! Schema::hasTable('wms_robot_units')) {
            Schema::create('wms_robot_units', function (Blueprint $table): void {
                $table->id();
                $table->string('robot_code', 64)->unique();
                $table->uuid('warehouse_id')->index();
                $table->string('kind', 32)->default('amr');
                $table->unsignedTinyInteger('battery_pct')->default(100);
                $table->string('status', 32)->default('idle');
                $table->string('current_zone_code', 64)->nullable();
                $table->unsignedInteger('max_speed_mm_s')->default(1800);
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->index(['warehouse_id', 'status'], 'wms_robot_units_wh_status_idx');
            });
        }

        if (! Schema::hasTable('wms_robot_missions')) {
            Schema::create('wms_robot_missions', function (Blueprint $table): void {
                $table->id();
                $table->string('mission_code', 64)->unique();
                $table->uuid('warehouse_id')->index();
                $table->unsignedBigInteger('robot_unit_id')->index();
                $table->unsignedBigInteger('wave_id')->nullable()->index();
                $table->string('zone_code', 64)->index();
                $table->string('status', 32)->default('queued');
                $table->boolean('fallback_manual')->default(false);
                $table->unsignedBigInteger('assigned_operator_user_id')->nullable()->index();
                $table->string('failure_reason', 128)->nullable();
                $table->string('certificate_hash', 64)->nullable();
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->foreign('robot_unit_id')->references('id')->on('wms_robot_units')->cascadeOnDelete();
                $table->foreign('wave_id')->references('id')->on('wms_waves')->nullOnDelete();
                $table->foreign('assigned_operator_user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['warehouse_id', 'status'], 'wms_robot_missions_wh_status_idx');
            });
        }

        if (! Schema::hasTable('wms_robot_zone_locks')) {
            Schema::create('wms_robot_zone_locks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('warehouse_id')->index();
                $table->string('zone_code', 64)->index();
                $table->unsignedBigInteger('robot_unit_id')->nullable()->index();
                $table->boolean('human_present')->default(false);
                $table->boolean('interlock_active')->default(false);
                $table->unsignedInteger('speed_limit_mm_s')->default(1800);
                $table->string('lock_status', 24)->default('active');
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->foreign('robot_unit_id')->references('id')->on('wms_robot_units')->nullOnDelete();
                $table->index(['warehouse_id', 'zone_code', 'lock_status'], 'wms_robot_zone_locks_wh_zone_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_robot_zone_locks');
        Schema::dropIfExists('wms_robot_missions');
        Schema::dropIfExists('wms_robot_units');
        Schema::dropIfExists('wms_robotics_plans');
    }
};

