<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 24.1 Store → Logistics: track source of shipment
        Schema::table('lgx_shipments', function (Blueprint $table) {
            $table->string('source_type', 64)->nullable()->after('invoice_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id']);
        });

        // 24.4 Resto → Logistics (cold-chain): temperature readings
        Schema::create('lgx_temperature_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('lgx_shipments')->cascadeOnDelete();
            $table->foreignId('container_id')->nullable()->constrained('lgx_containers')->nullOnDelete();
            $table->integer('temp_c10'); // temperature × 10, e.g. -185 = -18.5°C
            $table->integer('min_c10')->nullable();
            $table->integer('max_c10')->nullable();
            $table->boolean('excursion')->default(false);
            $table->string('recorded_by', 64)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['shipment_id', 'recorded_at']);
        });

        // 24.5 Mall → Logistics (loading dock): dock appointments
        Schema::create('lgx_dock_appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id'); // mall_properties.id
            $table->unsignedBigInteger('tenant_id')->nullable(); // mall_tenants.id
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('dock_code', 16);
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 16)->default('reserved'); // reserved, checked_in, completed, cancelled
            $table->string('vehicle_plate', 20)->nullable();
            $table->string('driver_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'dock_code', 'date', 'start_time'], 'lgx_dock_no_overlap');
            $table->index(['property_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_dock_appointments');
        Schema::dropIfExists('lgx_temperature_readings');

        Schema::table('lgx_shipments', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn(['source_type', 'source_id']);
        });
    }
};
