<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 76.1 Building zones & sensors
        Schema::create('prp_building_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone_code', 32)->unique();
            $table->string('building_code', 32);
            $table->string('name', 128);
            $table->string('floor_level', 16);
            $table->decimal('area_sqm', 8, 2);
            $table->decimal('current_temp_c', 4, 1)->default(25.0);
            $table->integer('current_occupancy')->default(0);
            $table->unsignedBigInteger('current_kwh_rate')->default(1500); // IDR per kWh (A3 integer money)
            $table->decimal('baseline_kwh_per_hour', 8, 2)->default(10.00);
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('prp_building_sensors', function (Blueprint $table) {
            $table->id();
            $table->string('sensor_code', 32);
            $table->unsignedBigInteger('zone_id');
            $table->string('sensor_type', 32); // TEMP, HUMIDITY, POWER_KWH, CO2, OCCUPANCY
            $table->decimal('reading_value', 10, 2);
            $table->string('idempotency_key', 64)->unique();
            $table->boolean('is_online')->default(true);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('zone_id')->references('id')->on('prp_building_zones')->cascadeOnDelete();
            $table->index(['zone_id', 'sensor_type', 'recorded_at']);
        });

        // 76.2 Automation commands (HVAC & Lighting)
        Schema::create('prp_hvac_commands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zone_id');
            $table->string('command_type', 32); // SET_TEMP, SETBACK, LIGHTING_OFF
            $table->decimal('target_value', 6, 2);
            $table->string('trigger_reason', 128);
            $table->decimal('estimated_kwh_saved', 8, 2)->default(0);
            $table->timestamps();

            $table->foreign('zone_id')->references('id')->on('prp_building_zones')->cascadeOnDelete();
        });

        // 76.3 Utility readings and invoices per tenant zone
        Schema::create('prp_zone_utility_billings', function (Blueprint $table) {
            $table->id();
            $table->string('billing_code', 32)->unique();
            $table->unsignedBigInteger('zone_id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->date('billing_date');
            $table->decimal('total_kwh', 10, 2);
            $table->unsignedInteger('overtime_ac_hours')->default(0);
            $table->unsignedBigInteger('overtime_ac_amount_idr')->default(0);
            $table->unsignedBigInteger('base_amount_idr')->default(0);
            $table->unsignedBigInteger('total_amount_idr');
            $table->decimal('carbon_emission_kg', 10, 2); // 76.4 GRK
            $table->string('status', 32)->default('BILLED');
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->foreign('zone_id')->references('id')->on('prp_building_zones')->cascadeOnDelete();
        });

        // 76.5 Prescriptive ops recommendations
        Schema::create('prp_prescriptive_ops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zone_id');
            $table->string('action_code', 32)->unique();
            $table->string('recommendation_text', 255);
            $table->decimal('potential_kwh_saved', 8, 2);
            $table->unsignedBigInteger('potential_saving_idr');
            $table->unsignedInteger('payback_months')->default(1);
            $table->string('status', 32)->default('PROPOSED'); // PROPOSED, APPROVED, APPLIED, REJECTED
            $table->timestamps();

            $table->foreign('zone_id')->references('id')->on('prp_building_zones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prp_prescriptive_ops');
        Schema::dropIfExists('prp_zone_utility_billings');
        Schema::dropIfExists('prp_hvac_commands');
        Schema::dropIfExists('prp_building_sensors');
        Schema::dropIfExists('prp_building_zones');
    }
};
