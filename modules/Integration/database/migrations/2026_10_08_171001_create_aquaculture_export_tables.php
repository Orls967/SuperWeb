<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 171.1: Lot passports with immutable lineage hash
        Schema::create('mar_lot_passports', function (Blueprint $table) {
            $table->id();
            $table->string('passport_code')->unique();
            $table->string('hatchery_code');
            $table->string('farm_cohort_code');
            $table->decimal('verified_harvest_weight_kg', 18, 2);
            $table->string('lineage_hash');
            $table->timestamps();
        });

        // 171.2: Export customs & certificates of origin
        Schema::create('mar_export_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_number')->unique();
            $table->string('passport_code');
            $table->decimal('export_quantity_kg', 18, 2);
            $table->string('destination_country_iso', 2);
            $table->boolean('health_cert_approved')->default(true);
            $table->timestamps();
        });

        // 171.3: Blue ESG verified credits & metrics
        Schema::create('mar_blue_esg_credits', function (Blueprint $table) {
            $table->id();
            $table->string('credit_code')->unique();
            $table->string('mangrove_restoration_polygon_id');
            $table->decimal('water_quality_index', 5, 2);
            $table->decimal('blue_carbon_tons', 18, 2);
            $table->boolean('has_satellite_evidence')->default(true);
            $table->boolean('issued')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mar_blue_esg_credits');
        Schema::dropIfExists('mar_export_certificates');
        Schema::dropIfExists('mar_lot_passports');
    }
};
