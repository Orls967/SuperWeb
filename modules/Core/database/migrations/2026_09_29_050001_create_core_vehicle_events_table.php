<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_vehicle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('core_vehicles')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('type'); // registered | acquired | service_completed | part_replaced | odometer_updated | ownership_transferred
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->char('prev_hash', 64);
            $table->char('hash', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vehicle_id', 'sequence']);
            $table->index(['vehicle_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_vehicle_events');
    }
};
