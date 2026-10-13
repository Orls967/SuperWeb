<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 215.2: Multi-echelon stock deployment & allocation limiting (allocated <= available)
        Schema::create('ops_sc_echelon_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('allocation_code')->unique();
            $table->string('sku_code');
            $table->string('hub_code'); // CENTRAL_DC, REGIONAL_DC, FORWARD_DARKSTORE
            $table->integer('available_stock_supply');
            $table->integer('allocated_quantity');
            $table->boolean('allocation_valid')->default(true);
            $table->timestamps();
        });

        // 215.3: Yard & dock scheduling across locations with conflict prevention
        Schema::create('ops_sc_dock_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_code')->unique();
            $table->string('facility_code');
            $table->string('dock_door_code');
            $table->string('slot_time_window'); // e.g. 2026-10-09 08:00-10:00
            $table->string('carrier_code');
            $table->string('status')->default('SCHEDULED'); // SCHEDULED, CANCELLED
            $table->unique(['facility_code', 'dock_door_code', 'slot_time_window'], 'ops_sc_dock_appts_fac_door_slot_uniq');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_sc_dock_appointments');
        Schema::dropIfExists('ops_sc_echelon_allocations');
    }
};
