<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_capacity_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('lgx_schedules')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('lgx_shipments')->nullOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->decimal('allocated_weight_kg', 12, 3);
            $table->unsignedInteger('allocated_volume_dm3');
            $table->unsignedInteger('allocated_teu')->default(0);
            $table->unsignedInteger('allocated_uld_positions')->default(0);
            $table->string('status', 32)->default('active'); // active, released
            $table->timestamps();

            $table->index(['schedule_id', 'status']);
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_capacity_reservations');
    }
};
