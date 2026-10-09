<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_climate_asset_exposures', function (Blueprint $table) {
            $table->id();
            $table->string('asset_site_code')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('primary_hazard_type'); // FLOOD, HEAT_STRESS, STORM_SURGE, DROUGHT
            $table->decimal('physical_risk_score', 4, 2); // 0 to 10
            $table->decimal('estimated_financial_loss_usd', 15, 2);
            $table->boolean('weather_sensor_online')->default(true);
            $table->string('weather_data_quality_label')->default('VERIFIED_SENSOR'); // VERIFIED_SENSOR, OFFICIAL_FALLBACK_UNCERTAIN (287.5)
            $table->timestamps();
        });

        Schema::create('esg_adaptation_measures', function (Blueprint $table) {
            $table->id();
            $table->string('measure_code')->unique();
            $table->string('asset_site_code')->index();
            $table->string('measure_name'); // FLOOD_BARRIER, BACKUP_SOLAR_STORAGE, ELEVATED_DC
            $table->decimal('capex_cost_usd', 15, 2);
            $table->decimal('avoided_loss_benefit_usd', 15, 2);
            $table->decimal('adaptation_roi_pct', 6, 2); // 287.2 & 287.4
            $table->boolean('measure_completed')->default(false);
            $table->boolean('insurance_resilience_credit_eligible')->default(false); // 287.7
            $table->timestamps();
        });

        Schema::create('esg_supply_chain_climate_risks', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code')->unique();
            $table->string('commodity_origin_region');
            $table->decimal('climate_vulnerability_score', 4, 2); // 0 to 10
            $table->boolean('mitigation_plan_triggered')->default(false); // 287.3 & 287.4
            $table->string('alternative_routing_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_supply_chain_climate_risks');
        Schema::dropIfExists('esg_adaptation_measures');
        Schema::dropIfExists('esg_climate_asset_exposures');
    }
};
