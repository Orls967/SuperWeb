<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Borehole Assays untuk estimasi cadangan blok tambang
        if (! Schema::hasTable('min_borehole_assays')) {
            Schema::create('min_borehole_assays', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('pit_id')->constrained('min_pits')->cascadeOnDelete();
                $table->string('hole_code', 32);
                $table->integer('coord_x_mm');
                $table->integer('coord_y_mm');
                $table->integer('elevation_z_mm');
                $table->unsignedInteger('depth_from_mm');
                $table->unsignedInteger('depth_to_mm');
                $table->unsignedInteger('grade_ni_ppm');
                $table->unsignedInteger('grade_fe_ppm');
                $table->unsignedInteger('grade_co_ppm');
                $table->string('lithology', 32); // LIMONITE, SAPROLITE, BEDROCK
                $table->timestamps();

                $table->index(['pit_id', 'hole_code']);
            });
        }

        // 2. Block Model Voxel Grid 3D
        if (! Schema::hasTable('min_block_model_voxels')) {
            Schema::create('min_block_model_voxels', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('pit_id')->constrained('min_pits')->cascadeOnDelete();
                $table->string('voxel_code', 64)->unique();
                $table->integer('center_x_mm');
                $table->integer('center_y_mm');
                $table->integer('center_z_mm');
                $table->unsignedInteger('volume_cm3');
                $table->unsignedInteger('density_kg_m3');
                $table->unsignedBigInteger('estimated_tonnage_kg');
                $table->unsignedInteger('estimated_grade_ppm');
                $table->string('classification', 16); // MEASURED, INDICATED, INFERRED, WASTE
                $table->string('status', 16)->default('UNMINED'); // UNMINED, EXCAVATED
                $table->timestamps();

                $table->index(['pit_id', 'status']);
            });
        }

        // 3. Tangki Timbun Bahan Bakar (Site Fuel Depot & Mobile Fuel Trucks)
        if (! Schema::hasTable('min_fuel_storages')) {
            Schema::create('min_fuel_storages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('storage_code', 32)->unique();
                $table->string('name', 64);
                $table->string('type', 16); // BULK_TANK, MOBILE_BOWSER
                $table->unsignedBigInteger('capacity_ml');
                $table->unsignedBigInteger('current_stock_ml');
                $table->unsignedBigInteger('minimum_safety_stock_ml');
                $table->string('status', 16)->default('ACTIVE');
                $table->timestamps();
            });
        }

        // 4. Log Dispense Bahan Bakar ke Alat Berat
        if (! Schema::hasTable('min_fuel_dispense_records')) {
            Schema::create('min_fuel_dispense_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('dispense_code', 32)->unique();
                $table->foreignUuid('storage_id')->constrained('min_fuel_storages')->cascadeOnDelete();
                $table->foreignUuid('equipment_id')->constrained('min_equipment')->cascadeOnDelete();
                $table->unsignedBigInteger('volume_ml');
                $table->unsignedBigInteger('equipment_engine_hours');
                $table->string('operator_ref', 64)->default('operator');
                $table->string('flowmeter_start_reading', 32);
                $table->string('flowmeter_end_reading', 32);
                $table->timestamp('dispensed_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('min_fuel_dispense_records');
        Schema::dropIfExists('min_fuel_storages');
        Schema::dropIfExists('min_block_model_voxels');
        Schema::dropIfExists('min_borehole_assays');
    }
};

