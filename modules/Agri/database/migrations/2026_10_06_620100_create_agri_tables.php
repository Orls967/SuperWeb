<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agri_farmers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('farmer_code')->unique();
            $table->string('farmer_group_name');
            $table->string('full_name');
            $table->string('phone_number');
            $table->string('land_polygon_geojson')->nullable();
            $table->decimal('land_area_hectares', 8, 2);
            $table->string('primary_commodity'); // cabe_merah, beras_organik, ayam_broiler, bawang_merah
            $table->string('status')->default('active'); // active, inactive, suspended
            $table->timestamps();
        });

        Schema::create('agri_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_number')->unique();
            $table->foreignUuid('farmer_id')->constrained('agri_farmers')->cascadeOnDelete();
            $table->string('commodity');
            $table->date('planting_date');
            $table->date('expected_harvest_date');
            $table->decimal('target_yield_kg', 12, 2);
            $table->bigInteger('seed_advance_value_idr')->default(0);
            $table->bigInteger('fertilizer_advance_value_idr')->default(0);
            $table->bigInteger('total_advance_deductible_idr')->default(0);
            $table->bigInteger('guaranteed_floor_price_idr_per_kg');
            $table->string('status')->default('active'); // draft, active, harvested, completed, default
            $table->timestamps();
        });

        Schema::create('agri_collection_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_number')->unique();
            $table->string('collection_center_id');
            $table->foreignUuid('contract_id')->constrained('agri_contracts')->cascadeOnDelete();
            $table->date('received_date');
            $table->decimal('gross_weight_kg', 12, 2);
            $table->string('grade'); // GRADE_A, GRADE_B, GRADE_C
            $table->decimal('moisture_percentage', 5, 2)->default(14.0);
            $table->bigInteger('buying_price_per_kg');
            $table->bigInteger('gross_payout_idr');
            $table->bigInteger('advance_deduction_idr')->default(0);
            $table->bigInteger('net_payout_idr');
            $table->string('payment_status')->default('pending'); // pending, paid
            $table->string('destination_unit')->default('RESTO_CK01'); // RESTO_CK01, MFG_PLANT_01, WMS_CENTRAL
            $table->timestamps();
        });

        Schema::create('agri_cold_chain_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('agri_collection_batches')->cascadeOnDelete();
            $table->string('reefer_truck_id');
            $table->dateTime('recorded_at');
            $table->decimal('temperature_celsius', 5, 2);
            $table->decimal('humidity_percentage', 5, 2);
            $table->string('gps_coordinates')->nullable();
            $table->string('cold_chain_status')->default('optimal'); // optimal, warning, breach
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agri_cold_chain_logs');
        Schema::dropIfExists('agri_collection_batches');
        Schema::dropIfExists('agri_contracts');
        Schema::dropIfExists('agri_farmers');
    }
};
