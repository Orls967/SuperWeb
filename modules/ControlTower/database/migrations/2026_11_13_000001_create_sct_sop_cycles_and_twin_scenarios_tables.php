<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sct_sop_cycles')) {
            Schema::create('sct_sop_cycles', function (Blueprint $table) {
                $table->id();
                $table->string('cycle_code', 32)->unique();
                $table->string('period', 7)->index(); // YYYY-MM
                $table->string('status', 32)->default('draft')->index();
                $table->string('scenario_type', 24)->default('moderate'); // conservative, moderate, aggressive
                $table->bigInteger('demand_plan_qty')->default(0);
                $table->bigInteger('supply_plan_qty')->default(0);
                $table->unsignedInteger('capacity_utilization_bps')->default(0);
                $table->bigInteger('gross_margin_est_idr')->default(0);
                $table->bigInteger('cash_impact_est_idr')->default(0);
                $table->unsignedBigInteger('signed_off_by_user_id')->nullable();
                $table->timestamp('signed_off_at')->nullable();
                $table->timestamps();

                $table->foreign('signed_off_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('sct_sop_events')) {
            Schema::create('sct_sop_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sop_cycle_id')->index();
                $table->string('from_status', 32);
                $table->string('to_status', 32);
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->string('notes', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('sop_cycle_id')->references('id')->on('sct_sop_cycles')->cascadeOnDelete();
                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('sct_forecast_overrides')) {
            Schema::create('sct_forecast_overrides', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('demand_forecast_id')->index();
                $table->bigInteger('previous_qty');
                $table->bigInteger('new_qty');
                $table->string('reason', 255);
                $table->unsignedBigInteger('overridden_by_user_id')->nullable();
                $table->timestamps();

                $table->foreign('demand_forecast_id')->references('id')->on('sct_demand_forecasts')->cascadeOnDelete();
                $table->foreign('overridden_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('sct_twin_scenarios')) {
            Schema::create('sct_twin_scenarios', function (Blueprint $table) {
                $table->id();
                $table->string('scenario_code', 32)->unique();
                $table->string('name', 120);
                $table->string('shock_type', 48); // PORT_CLOSURE, MATERIAL_INFLATION, PLANT_EXPANSION, DEMAND_SURGE
                $table->integer('shock_magnitude_bps');
                $table->unsignedInteger('duration_days');
                $table->unsignedInteger('baseline_otif_bps');
                $table->unsignedInteger('simulated_otif_bps');
                $table->unsignedInteger('at_risk_orders_count')->default(0);
                $table->bigInteger('estimated_margin_delta_idr')->default(0);
                $table->string('status', 24)->default('completed');
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();

                $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('sct_kpi_snapshots')) {
            Schema::create('sct_kpi_snapshots', function (Blueprint $table) {
                $table->id();
                $table->date('snapshot_date')->index();
                $table->unsignedInteger('otif_bps');
                $table->integer('cash_to_cash_days');
                $table->unsignedInteger('inventory_dos_days');
                $table->unsignedInteger('fulfillment_lead_time_hours');
                $table->unsignedInteger('logistics_cost_ratio_bps');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sct_safety_stock_policies')) {
            Schema::create('sct_safety_stock_policies', function (Blueprint $table) {
                $table->id();
                $table->string('item_code', 32)->unique();
                $table->string('abc_class', 1)->default('B');
                $table->string('xyz_class', 1)->default('Y');
                $table->unsignedInteger('service_level_bps')->default(9500);
                $table->unsignedBigInteger('safety_stock_qty')->default(0);
                $table->unsignedBigInteger('reorder_point_qty')->default(0);
                $table->boolean('slow_moving_flag')->default(false);
                $table->boolean('dead_stock_flag')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sct_safety_stock_policies');
        Schema::dropIfExists('sct_kpi_snapshots');
        Schema::dropIfExists('sct_twin_scenarios');
        Schema::dropIfExists('sct_forecast_overrides');
        Schema::dropIfExists('sct_sop_events');
        Schema::dropIfExists('sct_sop_cycles');
    }
};
