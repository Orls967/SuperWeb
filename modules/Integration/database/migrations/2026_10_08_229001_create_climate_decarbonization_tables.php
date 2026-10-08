<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_decarbonization_roadmaps', function (Blueprint $table) {
            $table->id();
            $table->string('roadmap_code')->unique();
            $table->string('business_line')->unique();
            $table->decimal('baseline_emissions_tco2e', 15, 2);
            $table->decimal('target_interim_emissions_tco2e', 15, 2);
            $table->decimal('current_actual_emissions_tco2e', 15, 2);
            $table->decimal('direct_abatement_achieved_tco2e', 15, 2)->default(0);
            $table->decimal('offset_applied_tco2e', 15, 2)->default(0);
            $table->decimal('min_abatement_ratio_pct', 5, 2)->default(80.00); // 229.6 edge case
            $table->string('status')->default('ON_TRACK');
            $table->timestamps();
        });

        Schema::create('esg_energy_transition_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('business_line')->index();
            $table->string('tech_type'); // SOLAR, WIND, BIOMASS, BATTERY_STORAGE
            $table->decimal('capex_required', 15, 2);
            $table->decimal('annual_savings', 15, 2);
            $table->decimal('expected_irr_pct', 5, 2);
            $table->decimal('annual_carbon_abatement_tco2e', 15, 2);
            $table->decimal('internal_shadow_carbon_price_usd', 8, 2)->default(50.00); // 229.3
            $table->decimal('shadow_carbon_value', 15, 2);
            $table->decimal('adjusted_economic_value', 15, 2);
            $table->decimal('prioritization_score', 8, 2);
            $table->string('funding_status')->default('PROPOSED');
            $table->timestamps();
        });

        Schema::create('esg_climate_asset_risks', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('business_line')->index();
            $table->string('location_name');
            $table->string('physical_risk_type'); // FLOOD, EXTREME_HEAT, SEA_LEVEL_RISE
            $table->string('transition_risk_type'); // CARBON_TAX, REGULATORY_BAN
            $table->string('risk_severity'); // LOW, MEDIUM, HIGH, CRITICAL
            $table->text('adaptation_plan');
            $table->string('linked_insurance_policy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('esg_scope3_emission_estimates', function (Blueprint $table) {
            $table->id();
            $table->string('estimate_code')->unique();
            $table->string('business_line')->index();
            $table->string('category_name');
            $table->decimal('estimated_tco2e', 15, 2);
            $table->string('emission_factor_version');
            $table->boolean('is_labeled_as_estimate')->default(true); // 229.7
            $table->boolean('claimed_as_measured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_scope3_emission_estimates');
        Schema::dropIfExists('esg_climate_asset_risks');
        Schema::dropIfExists('esg_energy_transition_projects');
        Schema::dropIfExists('esg_decarbonization_roadmaps');
    }
};
