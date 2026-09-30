<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_number', 64)->unique();
            $table->string('mode', 32); // ROAD, SEA, AIR
            $table->nullableMorphs('asset'); // asset_type, asset_id (Truck, Vessel, Aircraft)
            $table->foreignId('driver_id')->nullable()->constrained('lgx_drivers')->nullOnDelete();
            $table->string('voyage_number', 64)->nullable(); // Group identifier for multi-port voyages / flight trips
            $table->unsignedInteger('sequence')->default(1); // Leg sequence in voyage/trip
            $table->foreignId('origin_location_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->foreignId('destination_location_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->dateTime('etd');
            $table->dateTime('eta');
            $table->dateTime('cutoff_at');
            $table->dateTime('atd')->nullable();
            $table->dateTime('ata')->nullable();
            $table->string('status', 32)->default('scheduled');

            // Multi-dimensional capacity limits
            $table->decimal('cap_weight_kg', 12, 3);
            $table->unsignedInteger('cap_volume_dm3');
            $table->unsignedInteger('cap_teu')->default(0);
            $table->unsignedInteger('cap_uld_positions')->default(0);

            // Used capacity counters (monitored and verified)
            $table->decimal('used_weight_kg', 12, 3)->default(0);
            $table->unsignedInteger('used_volume_dm3')->default(0);
            $table->unsignedInteger('used_teu')->default(0);
            $table->unsignedInteger('used_uld_positions')->default(0);

            $table->timestamps();

            $table->index(['origin_location_id', 'destination_location_id', 'etd']);
            $table->index(['voyage_number', 'sequence']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_schedules');
    }
};
