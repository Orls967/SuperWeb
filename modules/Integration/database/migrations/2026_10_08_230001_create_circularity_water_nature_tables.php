<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_circularity_mass_balances', function (Blueprint $table) {
            $table->id();
            $table->string('balance_code')->unique();
            $table->string('business_line')->index();
            $table->decimal('total_input_material_tons', 15, 2);
            $table->decimal('virgin_material_tons', 15, 2);
            $table->decimal('recycled_content_tons', 15, 2);
            $table->decimal('waste_diverted_tons', 15, 2)->default(0);
            $table->decimal('product_takeback_tons', 15, 2)->default(0);
            $table->decimal('recycled_content_pct', 5, 2);
            $table->boolean('is_verified')->default(false);
            $table->boolean('green_premium_eligible')->default(false); // 230.7 anti-greenwashing
            $table->timestamps();
        });

        Schema::create('esg_water_stewardship_sites', function (Blueprint $table) {
            $table->id();
            $table->string('site_code')->unique();
            $table->string('business_line')->index();
            $table->string('location_name');
            $table->boolean('is_water_stressed_area')->default(false);
            $table->decimal('baseline_withdrawal_m3', 15, 2);
            $table->decimal('current_withdrawal_m3', 15, 2);
            $table->decimal('recycled_water_m3', 15, 2)->default(0);
            $table->decimal('discharged_water_m3', 15, 2)->default(0);
            $table->boolean('capex_fast_tracked')->default(false); // 230.6 edge case
            $table->boolean('quality_compliant')->default(true);
            $table->timestamps();
        });

        Schema::create('esg_nature_positive_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('entity_code')->index();
            $table->string('habitat_type'); // MANGROVE, RAINFOREST, PEATLAND, CORAL_REEF
            $table->decimal('restored_hectares', 10, 2);
            $table->decimal('operational_footprint_hectares', 10, 2);
            $table->decimal('net_positive_ratio', 5, 2);
            $table->string('verification_cycle_status')->default('PENDING'); // PENDING, VERIFIED, EXPIRED
            $table->timestamps();
        });

        Schema::create('esg_green_procurement_tenders', function (Blueprint $table) {
            $table->id();
            $table->string('tender_code')->unique();
            $table->string('vendor_code')->index();
            $table->decimal('commercial_score', 5, 2);
            $table->decimal('green_criteria_score', 5, 2);
            $table->boolean('mandatory_criteria_passed')->default(true);
            $table->decimal('composite_award_score', 5, 2);
            $table->boolean('awarded')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_green_procurement_tenders');
        Schema::dropIfExists('esg_nature_positive_projects');
        Schema::dropIfExists('esg_water_stewardship_sites');
        Schema::dropIfExists('esg_circularity_mass_balances');
    }
};
