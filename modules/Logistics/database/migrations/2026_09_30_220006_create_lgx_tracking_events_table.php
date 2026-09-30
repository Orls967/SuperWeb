<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('lgx_shipments')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('event_type', 64);
            $table->foreignId('location_id')->nullable()->constrained('lgx_locations')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 32)->nullable();
            $table->string('description', 500)->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('occurred_at');
            $table->string('prev_hash', 64);
            $table->string('hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['shipment_id', 'sequence']);
            $table->index(['shipment_id', 'occurred_at']);
            $table->index('hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_tracking_events');
    }
};
