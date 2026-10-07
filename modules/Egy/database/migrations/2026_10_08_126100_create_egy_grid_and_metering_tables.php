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
        Schema::create('egy_generation_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->string('asset_type'); // SOLAR_FARM, THERMAL_PLTU, BATTERY_BESS, GENSET
            $table->double('installed_capacity_mw', 10, 2);
            $table->double('current_output_mw', 10, 2)->default(0.0);
            $table->bigInteger('marginal_cost_per_mwh_minor')->default(0); // for merit order unit commitment
            $table->string('status')->default('ONLINE'); // ONLINE, STANDBY, CURTAILED, OFFLINE
            $table->timestamps();

            $table->index(['asset_type', 'status']);
        });

        Schema::create('egy_grid_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('node_code')->unique();
            $table->string('name');
            $table->string('substation_region');
            $table->double('nominal_voltage_kv', 6, 2);
            $table->double('current_load_mw', 10, 2)->default(0.0);
            $table->double('peak_capacity_mw', 10, 2);
            $table->timestamps();

            $table->index('substation_region');
        });

        Schema::create('egy_smart_meters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('meter_serial_number')->unique();
            $table->string('consumer_property_type'); // MALL, FACTORY, HOSPITAL, HOTEL, VENUE, OFFICE
            $table->string('consumer_property_id');
            $table->string('grid_node_id')->nullable();
            $table->double('total_kwh_accumulated', 14, 2)->default(0.0);
            $table->string('tariff_category')->default('BUSINESS_TOU'); // RESIDENTIAL, BUSINESS_TOU, INDUSTRIAL
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->index(['consumer_property_type', 'consumer_property_id']);
        });

        Schema::create('egy_meter_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('meter_id');
            $table->double('kwh_consumed', 10, 2);
            $table->boolean('is_peak_hour')->default(false);
            $table->bigInteger('rate_per_kwh_minor');
            $table->bigInteger('total_charge_minor');
            $table->timestamp('interval_start');
            $table->timestamp('interval_end');
            $table->timestamps();

            $table->index(['meter_id', 'interval_start']);
        });

        Schema::create('egy_ppa_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_number')->unique();
            $table->string('producer_entity_id');
            $table->string('grid_offtaker_id');
            $table->double('feed_in_tariff_per_kwh_minor', 10, 2);
            $table->double('surplus_kwh_exported', 12, 2)->default(0.0);
            $table->bigInteger('total_settlement_minor')->default(0);
            $table->string('status')->default('ACTIVE');
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['producer_entity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egy_ppa_contracts');
        Schema::dropIfExists('egy_meter_readings');
        Schema::dropIfExists('egy_smart_meters');
        Schema::dropIfExists('egy_grid_nodes');
        Schema::dropIfExists('egy_generation_assets');
    }
};
