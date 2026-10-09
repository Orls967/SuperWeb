<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autonomous_fleet_units', function (Blueprint $table) {
            $table->id();
            $table->string('unit_code')->unique();
            $table->string('vehicle_type'); // HAUL_TRUCK, AGV, AMR
            $table->string('operational_zone'); // MINE_PIT_A, PORT_BERTH_2, WAREHOUSE_CORRIDOR_5
            $table->decimal('current_speed_kmh', 5, 2);
            $table->decimal('safety_cage_speed_cap_kmh', 5, 2)->default(25.00); // 306.1 & 306.6
            $table->boolean('geofence_cage_active')->default(true); // 306.6 Cannot be disabled
            $table->boolean('emergency_stopped')->default(false); // 306.4 & 306.5
            $table->string('emergency_stop_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('autonomous_fleet_interventions', function (Blueprint $table) {
            $table->id();
            $table->string('intervention_code')->unique();
            $table->string('unit_code')->index();
            $table->string('remote_operator_id');
            $table->string('intervention_type'); // TELEOP_MANUAL_FALLBACK, PEDESTRIAN_CONGESTION_HOLD (306.2, 306.4, 306.5)
            $table->text('operator_action_notes');
            $table->boolean('safe_handover_verified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autonomous_fleet_interventions');
        Schema::dropIfExists('autonomous_fleet_units');
    }
};
