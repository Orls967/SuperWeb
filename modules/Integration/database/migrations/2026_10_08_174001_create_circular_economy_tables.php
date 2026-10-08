<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 174.1: Recycler facilities & environmental permits
        Schema::create('cir_recycler_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('facility_code')->unique();
            $table->string('operator_name');
            $table->boolean('has_hazardous_permit')->default(false); // B3 licensed operator
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 174.2 & 174.3: Industrial waste streams & reverse logistics manifests
        Schema::create('cir_waste_manifests', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_code')->unique();
            $table->string('source_entity_code'); // MINING, HOSPITAL, HOTEL, MFG
            $table->string('facility_code');
            $table->string('waste_type'); // E_WASTE, HAZARDOUS_B3, PLASTIC, SCRAP_METAL
            $table->boolean('is_hazardous')->default(false);
            $table->decimal('weight_tons', 18, 2);
            $table->string('manifest_hash');
            $table->string('status')->default('DISPATCHED'); // DISPATCHED, RECEIVED, TREATED
            $table->timestamps();
        });

        // 174.4: Circularity accounting & mass balance treatment
        Schema::create('cir_treatment_records', function (Blueprint $table) {
            $table->id();
            $table->string('treatment_code')->unique();
            $table->string('manifest_code');
            $table->decimal('input_weight_tons', 18, 2);
            $table->decimal('recycled_material_tons', 18, 2);
            $table->decimal('residual_waste_tons', 18, 2);
            $table->boolean('mass_balance_valid')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cir_treatment_records');
        Schema::dropIfExists('cir_waste_manifests');
        Schema::dropIfExists('cir_recycler_facilities');
    }
};
