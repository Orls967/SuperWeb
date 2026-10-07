<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('min_sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('site_code')->unique();
            $table->string('name');
            $table->string('commodity', 32); // NICKEL, COAL, COPPER, GOLD
            $table->string('location');
            $table->timestamps();
        });

        Schema::create('min_pits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('pit_code')->unique();
            $table->string('name');
            $table->unsignedBigInteger('target_production_ton')->default(100000);
            $table->unsignedBigInteger('actual_production_ton')->default(0);
            $table->timestamps();
        });

        Schema::create('min_equipment', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('equipment_code')->unique();
            $table->string('type', 32); // HAUL_TRUCK, EXCAVATOR, DRILL
            $table->string('model', 64);
            $table->decimal('capacity_ton', 8, 2);
            $table->string('status', 32)->default('available'); // available, dispatched, maintenance
            $table->unsignedInteger('engine_hours')->default(0);
            $table->timestamps();
        });

        Schema::create('min_dispatch_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->uuid('pit_id');
            $table->uuid('equipment_id');
            $table->string('shift_code', 32); // SHIFT_A, SHIFT_B
            $table->decimal('target_payload_ton', 8, 2);
            $table->decimal('actual_payload_ton', 8, 2)->default(0.00);
            $table->decimal('payload_variance_ton', 8, 2)->default(0.00);
            $table->decimal('fuel_consumed_liter', 8, 2)->default(0.00);
            $table->decimal('distance_km', 8, 2)->default(0.00);
            $table->boolean('fuel_anomaly_detected')->default(false);
            $table->boolean('contractor_payment_held')->default(false);
            $table->string('status', 32)->default('in_progress'); // in_progress, completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_dispatch_runs');
        Schema::dropIfExists('min_equipment');
        Schema::dropIfExists('min_pits');
        Schema::dropIfExists('min_sites');
    }
};
