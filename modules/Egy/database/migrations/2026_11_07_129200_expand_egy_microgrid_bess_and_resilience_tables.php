<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('egy_microgrids')) {
            Schema::create('egy_microgrids', function (Blueprint $table) {
                $table->id();
                $table->string('microgrid_code', 50)->unique();
                $table->string('site_name', 120);
                $table->string('operating_mode', 30)->default('grid_connected'); // grid_connected, islanded, blackout, restoring
                $table->unsignedBigInteger('total_capacity_kw')->default(0);
                $table->unsignedBigInteger('current_generation_kw')->default(0);
                $table->unsignedBigInteger('current_load_kw')->default(0);
                $table->unsignedBigInteger('grid_frequency_mhz')->default(50000); // 50.000 mHz = 50.00 Hz
                $table->unsignedBigInteger('bus_voltage_volt')->default(20000); // 20.000 V
                $table->boolean('is_black_start_capable')->default(true);
                $table->timestamps();

                $table->index(['operating_mode', 'is_black_start_capable']);
            });
        }

        if (! Schema::hasTable('egy_microgrid_feeders')) {
            Schema::create('egy_microgrid_feeders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('microgrid_id')->constrained('egy_microgrids')->cascadeOnDelete();
                $table->string('feeder_code', 50);
                $table->string('feeder_name', 120);
                $table->string('tier', 30); // TIER_1_CRITICAL, TIER_2_ESSENTIAL, TIER_3_COMMERCIAL, TIER_4_FLEXIBLE
                $table->unsignedInteger('priority_rank')->default(1);
                $table->unsignedBigInteger('rated_load_kw');
                $table->boolean('is_energized')->default(true);
                $table->timestamps();

                $table->unique(['microgrid_id', 'feeder_code']);
                $table->index(['microgrid_id', 'priority_rank']);
            });
        }

        if (! Schema::hasTable('egy_bess_assets')) {
            Schema::create('egy_bess_assets', function (Blueprint $table) {
                $table->id();
                $table->string('bess_code', 50)->unique();
                $table->string('site_name', 120);
                $table->string('battery_chemistry', 30)->default('LFP'); // LFP, NMC, FLOW
                $table->unsignedBigInteger('capacity_kwh');
                $table->unsignedBigInteger('max_charge_power_kw');
                $table->unsignedBigInteger('max_discharge_power_kw');
                $table->unsignedInteger('current_soc_bps')->default(5000); // 50%
                $table->unsignedInteger('state_of_health_bps')->default(10000); // 100%
                $table->unsignedInteger('cycle_count')->default(0);
                $table->unsignedBigInteger('replacement_cost_idr')->default(0);
                $table->string('status', 30)->default('operational');
                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('egy_bess_dispatches')) {
            Schema::create('egy_bess_dispatches', function (Blueprint $table) {
                $table->id();
                $table->string('dispatch_number', 60)->unique();
                $table->foreignId('bess_asset_id')->constrained('egy_bess_assets')->cascadeOnDelete();
                $table->string('dispatch_type', 30); // arbitrage, peak_shaving, frequency_support, black_start
                $table->unsignedBigInteger('energy_kwh');
                $table->unsignedBigInteger('charging_cost_idr')->default(0);
                $table->unsignedBigInteger('discharging_revenue_idr')->default(0);
                $table->unsignedBigInteger('degradation_cost_idr')->default(0);
                $table->bigInteger('net_profit_idr')->default(0);
                $table->unsignedBigInteger('ledger_transaction_id')->nullable();
                $table->string('certificate_hash', 64)->nullable();
                $table->timestamps();

                $table->index(['bess_asset_id', 'dispatch_type']);
                $table->index('ledger_transaction_id');
            });
        }

        if (! Schema::hasTable('egy_generator_logs')) {
            Schema::create('egy_generator_logs', function (Blueprint $table) {
                $table->id();
                $table->string('generator_asset_code', 50);
                $table->string('site_name', 120);
                $table->string('run_type', 40); // weekly_no_load_test, weekly_load_test, grid_outage_support, black_start
                $table->timestamp('start_time');
                $table->timestamp('end_time')->nullable();
                $table->unsignedInteger('duration_minutes')->default(0);
                $table->unsignedBigInteger('fuel_consumed_liters')->default(0);
                $table->unsignedInteger('load_percentage_bps')->default(0);
                $table->unsignedBigInteger('emissions_kg_co2e')->default(0);
                $table->boolean('is_passed')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['generator_asset_code', 'run_type']);
                $table->index(['is_passed', 'start_time']);
            });
        }

        if (! Schema::hasTable('egy_demand_response_events')) {
            Schema::create('egy_demand_response_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_code', 50)->unique();
                $table->string('curtailment_signal_code', 60);
                $table->timestamp('start_time');
                $table->unsignedBigInteger('target_curtailment_kw');
                $table->unsignedBigInteger('achieved_curtailment_kw');
                $table->unsignedInteger('response_time_seconds')->default(0);
                $table->unsignedBigInteger('incentive_revenue_idr')->default(0);
                $table->string('status', 30)->default('completed'); // completed, failed, cancelled
                $table->unsignedBigInteger('ledger_transaction_id')->nullable();
                $table->timestamps();

                $table->index(['status', 'start_time']);
                $table->index('ledger_transaction_id');
            });
        }

        if (! Schema::hasTable('egy_black_start_plans')) {
            Schema::create('egy_black_start_plans', function (Blueprint $table) {
                $table->id();
                $table->string('plan_code', 50)->unique();
                $table->foreignId('microgrid_id')->constrained('egy_microgrids')->cascadeOnDelete();
                $table->string('status', 30)->default('draft'); // draft, planned, executing, completed, failed
                $table->unsignedInteger('total_components_count')->default(0);
                $table->unsignedInteger('total_estimated_seconds')->default(0);
                $table->json('step_sequence_json');
                $table->json('execution_log_json')->nullable();
                $table->string('certificate_hash', 64);
                $table->timestamps();

                $table->index(['microgrid_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('egy_black_start_plans');
        Schema::dropIfExists('egy_demand_response_events');
        Schema::dropIfExists('egy_generator_logs');
        Schema::dropIfExists('egy_bess_dispatches');
        Schema::dropIfExists('egy_bess_assets');
        Schema::dropIfExists('egy_microgrid_feeders');
        Schema::dropIfExists('egy_microgrids');
    }
};
