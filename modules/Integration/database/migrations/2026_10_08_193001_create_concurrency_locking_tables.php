<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 193.1: Consistent lock-ordered hot resource allocation (anti-deadlock, zero over-allocation)
        Schema::create('scl_contention_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type'); // SEAT, ROOM, INVENTORY_SKU, FLEET
            $table->string('resource_id');
            $table->integer('total_capacity');
            $table->integer('allocated_units')->default(0);
            $table->timestamps();
        });

        // 193.2: Optimistic concurrency control for non-monetary documents (contracts, schedules)
        Schema::create('scl_optimistic_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_code')->unique();
            $table->string('content_payload');
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        // 193.3: Admission rate control (rate shed on extreme traffic)
        Schema::create('scl_admission_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint_key')->unique();
            $table->integer('max_requests_per_second');
            $table->integer('current_second_requests')->default(0);
            $table->boolean('is_shedding_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scl_admission_rate_limits');
        Schema::dropIfExists('scl_optimistic_documents');
        Schema::dropIfExists('scl_contention_allocations');
    }
};
