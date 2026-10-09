<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 80.1 Cold chain temperature breach logs & payment holds
        Schema::create('lgx_temp_breach_holds', function (Blueprint $table) {
            $table->id();
            $table->string('breach_code', 32)->unique();
            $table->string('shipment_code', 32);
            $table->unsignedBigInteger('carrier_id');
            $table->decimal('recorded_temp_c', 5, 2);
            $table->decimal('max_allowed_temp_c', 5, 2);
            $table->integer('duration_minutes');
            $table->bigInteger('hold_amount_idr');
            $table->string('hash_proof', 64);
            $table->string('status', 32)->default('HELD'); // HELD, RELEASED, FORFEITED_AS_CLAIM
            $table->timestamps();
        });

        // 80.3 Drone units & robotics
        Schema::create('lgx_drone_units', function (Blueprint $table) {
            $table->id();
            $table->string('drone_code', 32)->unique();
            $table->string('model_name', 64);
            $table->integer('battery_percent')->default(100);
            $table->decimal('max_payload_kg', 5, 2)->default(5.0);
            $table->decimal('max_range_km', 5, 2)->default(15.0);
            $table->string('status', 32)->default('IDLE'); // IDLE, IN_MISSION, CHARGING, MAINTENANCE
            $table->timestamps();
        });

        // 80.3 & 80.4 Drone missions & proof of delivery (POD)
        Schema::create('lgx_drone_missions', function (Blueprint $table) {
            $table->id();
            $table->string('mission_code', 32)->unique();
            $table->unsignedBigInteger('drone_unit_id');
            $table->string('shipment_code', 32);
            $table->decimal('package_weight_kg', 5, 2);
            $table->decimal('distance_km', 5, 2);
            $table->string('destination_geo_hash', 32);
            $table->string('status', 32)->default('DISPATCHED'); // DISPATCHED, DELIVERED, FALLBACK_DRIVER, FAILED
            $table->string('pod_signature_hash', 64)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('drone_unit_id')->references('id')->on('lgx_drone_units')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_drone_missions');
        Schema::dropIfExists('lgx_drone_units');
        Schema::dropIfExists('lgx_temp_breach_holds');
    }
};
