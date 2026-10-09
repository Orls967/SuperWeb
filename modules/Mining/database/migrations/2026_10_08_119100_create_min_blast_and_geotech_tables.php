<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 119.2 Blast Management and Safety Radius
        Schema::create('min_blast_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('blast_code', 32)->unique();
            $table->uuid('pit_id');
            $table->dateTime('scheduled_at');
            $table->decimal('blast_coord_lat', 10, 6);
            $table->decimal('blast_coord_lng', 10, 6);
            $table->decimal('safety_radius_meters', 8, 2)->default(500.0);
            $table->integer('holes_count');
            $table->decimal('explosives_kg', 10, 2);
            $table->decimal('oversize_fragmentation_percent', 5, 2)->default(0); // If > 15%, drill pattern must be corrected
            $table->string('status', 32)->default('SCHEDULED'); // SCHEDULED, DETONATED, CANCELLED, BLOCKED
            $table->timestamps();

            $table->foreign('pit_id')->references('id')->on('min_pits')->cascadeOnDelete();
        });

        // 119.3 Geotech Slope Monitoring
        Schema::create('min_slope_stability_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sensor_code', 32);
            $table->uuid('pit_id');
            $table->decimal('displacement_mm', 8, 2);
            $table->decimal('velocity_mm_day', 8, 2);
            $table->boolean('evacuation_alarm_triggered')->default(false);
            $table->string('alert_level', 16)->default('GREEN'); // GREEN, YELLOW, RED_SHUTDOWN
            $table->timestamps();

            $table->foreign('pit_id')->references('id')->on('min_pits')->cascadeOnDelete();
        });

        // 119.5 Barge & Marine Quarry Transport
        Schema::create('min_barge_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('barge_code', 32)->unique();
            $table->string('vessel_name', 128);
            $table->string('destination_port', 64);
            $table->decimal('manifest_tonnage', 10, 2);
            $table->decimal('weighbridge_tonnage', 10, 2);
            $table->bigInteger('freight_tariff_idr');
            $table->string('custody_hash', 64);
            $table->string('status', 32)->default('LOADED'); // LOADED, IN_TRANSIT, DISCHARGED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_barge_shipments');
        Schema::dropIfExists('min_slope_stability_readings');
        Schema::dropIfExists('min_blast_schedules');
    }
};
