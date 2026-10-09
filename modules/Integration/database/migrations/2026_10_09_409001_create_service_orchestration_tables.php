<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_orchestration_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_order_code')->unique();
            $table->string('customer_id');
            $table->decimal('total_amount', 18, 2);
            $table->string('orchestration_state')->default('pending'); // pending, reserved, partially_fulfilled, fulfilled, rolled_back
            $table->boolean('has_orphan_reservation')->default(false); // 409.4, 409.6
            $table->timestamps();
        });

        Schema::create('ops_orchestration_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('ops_orchestration_bundles')->cascadeOnDelete();
            $table->string('component_type'); // e.g. hotel, flight, venue, auto_rental
            $table->string('reservation_reference')->unique();
            $table->decimal('amount', 18, 2);
            $table->string('status')->default('pending'); // pending, reserved, failed, refunded
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_orchestration_components');
        Schema::dropIfExists('ops_orchestration_bundles');
    }
};
