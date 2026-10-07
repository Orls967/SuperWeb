<?php

declare(strict_types=1);

namespace Modules\Egy\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egy_microgrid_controllers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('microgrid_code')->unique();
            $table->string('site_name');
            $table->string('grid_mode')->default('GRID_CONNECTED'); // GRID_CONNECTED, ISLANDED
            $table->double('available_power_kw', 10, 2)->default(0.0);
            $table->timestamps();
        });

        Schema::create('egy_microgrid_load_priorities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('microgrid_id');
            $table->string('load_name');
            $table->string('tier'); // TIER_1_HOSPITAL, TIER_2_CRITICAL_FACTORY, TIER_3_MALL, TIER_4_GENERAL
            $table->integer('priority_rank'); // 1 = highest priority
            $table->double('required_load_kw', 10, 2);
            $table->boolean('is_shed')->default(false);
            $table->timestamps();

            $table->index(['microgrid_id', 'priority_rank']);
        });

        Schema::create('egy_bess_arbitrage_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bess_asset_id');
            $table->string('run_code')->unique();
            $table->double('energy_discharged_kwh', 10, 2);
            $table->bigInteger('charging_cost_minor');
            $table->bigInteger('discharging_revenue_minor');
            $table->bigInteger('net_arbitrage_profit_minor');
            $table->integer('battery_cycle_count_increment')->default(1);
            $table->double('state_of_health_pct', 5, 2)->default(100.0);
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index('bess_asset_id');
        });

        Schema::create('egy_backup_generator_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('test_code')->unique();
            $table->string('property_id');
            $table->string('generator_asset_id');
            $table->timestamp('scheduled_test_date');
            $table->double('load_test_pct', 5, 2);
            $table->integer('runtime_minutes');
            $table->boolean('is_passed')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'is_passed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egy_backup_generator_tests');
        Schema::dropIfExists('egy_bess_arbitrage_runs');
        Schema::dropIfExists('egy_microgrid_load_priorities');
        Schema::dropIfExists('egy_microgrid_controllers');
    }
};
