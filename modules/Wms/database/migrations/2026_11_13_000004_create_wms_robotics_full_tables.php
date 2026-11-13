<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wms_robots')) {
            Schema::create('wms_robots', function (Blueprint $table): void {
                $table->id();
                $table->string('robot_code', 64)->unique();
                $table->uuid('warehouse_id')->index();
                $table->string('robot_type', 32)->default('amr');
                $table->unsignedInteger('capacity_units')->default(100);
                $table->unsignedTinyInteger('battery_pct')->default(100);
                $table->boolean('is_online')->default(true);
                $table->unsignedInteger('position_x')->default(0);
                $table->unsignedInteger('position_y')->default(0);
                $table->string('current_zone_code', 64)->nullable();
                $table->string('device_mode', 32)->default('simulated_device');
                $table->unsignedInteger('health_score')->default(1000);
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->index(['warehouse_id', 'is_online'], 'wms_robots_wh_online_idx');
            });
        }

        if (! Schema::hasTable('wms_traffic_zones')) {
            Schema::create('wms_traffic_zones', function (Blueprint $table): void {
                $table->id();
                $table->uuid('warehouse_id')->index();
                $table->string('zone_code', 64);
                $table->string('zone_kind', 32)->default('corridor');
                $table->unsignedInteger('capacity_k')->default(1);
                $table->boolean('staff_blocked')->default(false);
                $table->unsignedInteger('block_start_min')->nullable();
                $table->unsignedInteger('block_end_min')->nullable();
                $table->string('block_reason', 191)->nullable();
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->unique(['warehouse_id', 'zone_code'], 'wms_traffic_zones_wh_code_uq');
            });
        }

        if (Schema::hasTable('wms_robot_missions')) {
            Schema::table('wms_robot_missions', function (Blueprint $table): void {
                if (! Schema::hasColumn('wms_robot_missions', 'robot_id')) {
                    $table->unsignedBigInteger('robot_id')->nullable()->after('robot_unit_id')->index();
                    $table->foreign('robot_id')->references('id')->on('wms_robots')->nullOnDelete();
                }
                if (! Schema::hasColumn('wms_robot_missions', 'traffic_zone_id')) {
                    $table->unsignedBigInteger('traffic_zone_id')->nullable()->after('zone_code')->index();
                    $table->foreign('traffic_zone_id')->references('id')->on('wms_traffic_zones')->nullOnDelete();
                }
                if (! Schema::hasColumn('wms_robot_missions', 'warehouse_task_id')) {
                    $table->unsignedBigInteger('warehouse_task_id')->nullable()->after('wave_id')->index();
                    $table->foreign('warehouse_task_id')->references('id')->on('wms_tasks')->nullOnDelete();
                }
                if (! Schema::hasColumn('wms_robot_missions', 'retry_count')) {
                    $table->unsignedTinyInteger('retry_count')->default(0)->after('fallback_manual');
                }
                if (! Schema::hasColumn('wms_robot_missions', 'priority')) {
                    $table->unsignedInteger('priority')->default(100)->after('retry_count');
                }
                if (! Schema::hasColumn('wms_robot_missions', 'scheduled_start_min')) {
                    $table->unsignedInteger('scheduled_start_min')->nullable()->after('priority');
                }
                if (! Schema::hasColumn('wms_robot_missions', 'duration_min')) {
                    $table->unsignedInteger('duration_min')->default(5)->after('scheduled_start_min');
                }
            });
        }

        if (! Schema::hasTable('wms_zone_reservations')) {
            Schema::create('wms_zone_reservations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('warehouse_id')->index();
                $table->unsignedBigInteger('traffic_zone_id')->index();
                $table->unsignedBigInteger('mission_id')->nullable()->index();
                $table->unsignedInteger('start_min');
                $table->unsignedInteger('end_min');
                $table->string('reservation_kind', 32)->default('mission');
                $table->string('status', 32)->default('active');
                $table->timestamp('released_at')->nullable();
                $table->string('release_reason', 191)->nullable();
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->foreign('traffic_zone_id')->references('id')->on('wms_traffic_zones')->cascadeOnDelete();
                $table->foreign('mission_id')->references('id')->on('wms_robot_missions')->nullOnDelete();
                $table->index(['traffic_zone_id', 'status', 'start_min', 'end_min'], 'wms_zone_res_interval_idx');
            });
        }

        if (! Schema::hasTable('wms_robot_mission_events')) {
            Schema::create('wms_robot_mission_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('mission_id')->index();
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->string('reason_code', 64)->nullable();
                $table->string('reason_note', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('recorded_at');

                $table->foreign('mission_id')->references('id')->on('wms_robot_missions')->cascadeOnDelete();
                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['mission_id', 'recorded_at'], 'wms_robot_mission_events_hist_idx');
            });
        }

        if (! Schema::hasTable('wms_robot_waves')) {
            Schema::create('wms_robot_waves', function (Blueprint $table): void {
                $table->id();
                $table->uuid('warehouse_id')->index();
                $table->string('wave_code', 64)->unique();
                $table->string('carrier_code', 64)->index();
                $table->unsignedInteger('cutoff_min');
                $table->unsignedInteger('capacity');
                $table->unsignedInteger('allocated_load');
                $table->boolean('is_oversize')->default(false);
                $table->string('status', 32)->default('planned');
                $table->json('order_items')->nullable();
                $table->string('certificate_hash', 64);
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->index(['warehouse_id', 'carrier_code', 'cutoff_min'], 'wms_robot_waves_carrier_cutoff_idx');
            });
        }

        if (! Schema::hasTable('wms_robotics_roi_proposals')) {
            Schema::create('wms_robotics_roi_proposals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('warehouse_id')->index();
                $table->string('proposal_code', 64)->unique();
                $table->string('title', 191);
                $table->unsignedBigInteger('investment_minor');
                $table->json('cash_flows_minor');
                $table->unsignedInteger('discount_rate_num');
                $table->unsignedInteger('discount_rate_den');
                $table->bigInteger('npv_minor');
                $table->unsignedInteger('payback_months')->nullable();
                $table->string('recommendation', 32);
                $table->string('status', 32)->default('draft');
                $table->string('parameter_label', 32)->default('asumsi_simulasi');
                $table->string('justification', 255)->nullable();
                $table->string('certificate_hash', 64);
                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('wms_robot_charge_schedules')) {
            Schema::create('wms_robot_charge_schedules', function (Blueprint $table): void {
                $table->id();
                $table->uuid('warehouse_id')->index();
                $table->unsignedBigInteger('robot_id')->index();
                $table->unsignedInteger('window_start_min');
                $table->unsignedInteger('window_end_min');
                $table->unsignedTinyInteger('initial_battery_pct');
                $table->unsignedTinyInteger('target_battery_pct');
                $table->unsignedInteger('min_fleet_available');
                $table->string('status', 32)->default('scheduled');
                $table->string('idempotency_key', 128)->unique();
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->foreign('robot_id')->references('id')->on('wms_robots')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('wms_robot_telemetries')) {
            Schema::create('wms_robot_telemetries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('warehouse_id')->index();
                $table->unsignedBigInteger('robot_id')->index();
                $table->unsignedTinyInteger('battery_pct');
                $table->unsignedInteger('error_count')->default(0);
                $table->unsignedInteger('downtime_minutes')->default(0);
                $table->unsignedInteger('health_score');
                $table->string('anomaly_code', 64)->nullable();
                $table->string('idempotency_key', 128)->unique();
                $table->timestamp('recorded_at');

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->foreign('robot_id')->references('id')->on('wms_robots')->cascadeOnDelete();
                $table->index(['robot_id', 'recorded_at'], 'wms_robot_tel_robot_time_idx');
            });
        }

        if (! Schema::hasTable('wms_robot_safety_incidents')) {
            Schema::create('wms_robot_safety_incidents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('warehouse_id')->index();
                $table->string('incident_code', 64)->unique();
                $table->unsignedBigInteger('mission_id')->nullable()->index();
                $table->unsignedBigInteger('traffic_zone_id')->nullable()->index();
                $table->unsignedBigInteger('staff_user_id')->nullable()->index();
                $table->string('severity', 32);
                $table->string('incident_type', 64);
                $table->string('description', 500);
                $table->string('status', 32)->default('reported');
                $table->string('simulation_label', 32)->default('asumsi_simulasi');
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->index();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('recorded_at');

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->foreign('mission_id')->references('id')->on('wms_robot_missions')->nullOnDelete();
                $table->foreign('traffic_zone_id')->references('id')->on('wms_traffic_zones')->nullOnDelete();
                $table->foreign('staff_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('reviewed_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_robot_safety_incidents');
        Schema::dropIfExists('wms_robot_telemetries');
        Schema::dropIfExists('wms_robot_charge_schedules');
        Schema::dropIfExists('wms_robotics_roi_proposals');
        Schema::dropIfExists('wms_robot_waves');
        Schema::dropIfExists('wms_robot_mission_events');
        Schema::dropIfExists('wms_zone_reservations');
        Schema::dropIfExists('wms_traffic_zones');
        Schema::dropIfExists('wms_robots');
    }
};
