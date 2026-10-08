<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 170.1: Marine aquaculture cohorts & biomass
        Schema::create('mar_cohorts', function (Blueprint $table) {
            $table->id();
            $table->string('cohort_code')->unique();
            $table->string('species_name'); // SHRIMP_VANNAMEI, TILAPIA, BARRAMUNDI, TUNA
            $table->decimal('total_biomass_kg', 18, 2);
            $table->decimal('harvested_biomass_kg', 18, 2)->default(0.00);
            $table->string('health_status')->default('HEALTHY'); // HEALTHY, QUARANTINED, DISEASED
            $table->timestamps();
        });

        // 170.3: Catch & harvest chain of custody with cold chain temperature
        Schema::create('mar_harvest_lots', function (Blueprint $table) {
            $table->id();
            $table->string('harvest_lot_code')->unique();
            $table->string('cohort_code');
            $table->decimal('harvest_weight_kg', 18, 2);
            $table->decimal('cold_chain_temp_c', 5, 2);
            $table->boolean('cold_chain_breached')->default(false);
            $table->string('status')->default('CLEARED'); // CLEARED, QUARANTINED
            $table->timestamps();
        });

        // 170.3: Sustainable quota allocations per vessel/zone
        Schema::create('mar_sustainable_quotas', function (Blueprint $table) {
            $table->id();
            $table->string('zone_code')->unique();
            $table->decimal('annual_quota_kg', 18, 2);
            $table->decimal('accumulated_harvest_kg', 18, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mar_sustainable_quotas');
        Schema::dropIfExists('mar_harvest_lots');
        Schema::dropIfExists('mar_cohorts');
    }
};
