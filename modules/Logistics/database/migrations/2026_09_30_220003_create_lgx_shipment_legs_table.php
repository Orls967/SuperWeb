<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_shipment_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('lgx_shipments')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('lgx_schedules')->nullOnDelete();
            $table->unsignedInteger('leg_sequence')->default(1);
            $table->string('mode', 32); // ROAD, SEA, AIR
            $table->foreignId('origin_location_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->foreignId('destination_location_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->dateTime('estimated_departure');
            $table->dateTime('estimated_arrival');
            $table->dateTime('actual_departure')->nullable();
            $table->dateTime('actual_arrival')->nullable();
            $table->string('status', 32)->default('pending'); // pending, active, completed, cancelled
            $table->timestamps();

            $table->unique(['shipment_id', 'leg_sequence']);
            $table->index(['shipment_id', 'status']);
            $table->index('schedule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_shipment_legs');
    }
};
