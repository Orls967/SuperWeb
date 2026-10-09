<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_water_energy_baselines', function (Blueprint $table) {
            $table->id();
            $table->string('site_code')->unique();
            $table->string('site_name');
            $table->decimal('production_units', 12, 2);
            $table->decimal('total_water_consumption_m3', 15, 2);
            $table->decimal('total_energy_kwh', 15, 2);
            $table->decimal('normalized_water_intensity_m3_per_unit', 10, 4); // 446.1, 446.6 normalized intensity
            $table->decimal('normalized_energy_intensity_kwh_per_unit', 10, 4);
            $table->timestamps();
        });

        Schema::create('esg_energy_efficiency_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('site_code');
            $table->decimal('baseline_consumption_kwh', 15, 2);
            $table->decimal('measured_post_kwh', 15, 2)->nullable();
            $table->decimal('verified_kwh_savings', 15, 2)->default(0.00); // 446.2, 446.4
            $table->boolean('mv_measurement_verified')->default(false);
            $table->boolean('sustainment_audit_completed')->default(false); // 446.5 edge case (6/12 month review)
            $table->string('status')->default('implemented'); // implemented, mv_verified, sustained
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_energy_efficiency_projects');
        Schema::dropIfExists('esg_water_energy_baselines');
    }
};
