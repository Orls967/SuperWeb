<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_zone_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('zone_id'); // e.g. AISLE_A_BAY_04
            $table->string('active_robot_id')->nullable();
            $table->boolean('is_reserved')->default(false);
            $table->boolean('safety_interlock_active')->default(true); // 279.6
            $table->boolean('human_present_in_zone')->default(false); // 279.6
            $table->timestamps();
        });

        Schema::create('warehouse_robot_fleet', function (Blueprint $table) {
            $table->id();
            $table->string('robot_code')->unique();
            $table->string('model_type'); // AMR_500KG, AGV_FORKLIFT
            $table->integer('battery_level_pct')->default(100);
            $table->string('status')->default('IDLE'); // IDLE, ASSIGNED, CHARGING, FAILED_DOWN
            $table->boolean('manual_fallback_active')->default(false); // 279.2, 279.4, 279.5
            $table->timestamps();
        });

        Schema::create('warehouse_picking_waves', function (Blueprint $table) {
            $table->id();
            $table->string('wave_code')->unique();
            $table->string('assigned_robot_id')->nullable();
            $table->integer('total_items_count');
            $table->decimal('pick_capacity_limit', 10, 2);
            $table->boolean('is_re为其plann_required')->default(false); // 279.5
            $table->string('status')->default('SCHEDULED'); // SCHEDULED, IN_PROGRESS, FALLBACK_MANUAL, COMPLETED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_picking_waves');
        Schema::dropIfExists('warehouse_robot_fleet');
        Schema::dropIfExists('warehouse_zone_reservations');
    }
};
