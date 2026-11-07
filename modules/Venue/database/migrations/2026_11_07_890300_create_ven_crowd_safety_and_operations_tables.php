<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ven_crowd_crush_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code', 50)->unique();
            $table->foreignId('zone_id')->constrained('ven_entertainment_zones')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('ven_entertainment_events')->cascadeOnDelete();
            $table->string('risk_level', 30);
            $table->integer('headcount_at_incident')->default(0);
            $table->string('evacuation_corridor', 100)->nullable();
            $table->timestamp('lockdown_initiated_at')->nullable();
            $table->boolean('ambulance_dispatched')->default(false);
            $table->string('status', 30)->default('reported');
            $table->foreignId('reported_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('ven_bag_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('inspection_code', 50)->unique();
            $table->foreignId('event_id')->constrained('ven_entertainment_events')->cascadeOnDelete();
            $table->string('gate_code', 50);
            $table->foreignId('patron_ticket_id')->nullable()->constrained('ven_entertainment_tickets')->nullOnDelete();
            $table->string('inspector_badge', 50);
            $table->boolean('contraband_found')->default(false);
            $table->string('contraband_type', 50)->nullable();
            $table->boolean('police_referred')->default(false);
            $table->string('status', 30)->default('cleared');
            $table->timestamp('inspected_at');
            $table->timestamps();
        });

        Schema::create('ven_zone_relocation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('relocation_code', 50)->unique();
            $table->foreignId('event_id')->constrained('ven_entertainment_events')->cascadeOnDelete();
            $table->foreignId('from_zone_id')->constrained('ven_entertainment_zones')->cascadeOnDelete();
            $table->foreignId('to_zone_id')->constrained('ven_entertainment_zones')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('ven_entertainment_tickets')->cascadeOnDelete();
            $table->string('reason', 150);
            $table->integer('refund_amount_idr')->default(0);
            $table->string('status', 30)->default('completed');
            $table->timestamps();
        });

        Schema::create('ven_turnstile_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_code', 50)->unique();
            $table->foreignId('zone_id')->constrained('ven_entertainment_zones')->cascadeOnDelete();
            $table->string('hardware_serial', 100);
            $table->string('firmware_version', 30)->default('v1.0.0');
            $table->string('ip_address', 45);
            $table->string('turnstile_status', 30)->default('ONLINE');
            $table->integer('optical_beam_trips')->default(0);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ven_table_bottle_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('ven_entertainment_table_bookings')->cascadeOnDelete();
            $table->string('bottle_sku', 50);
            $table->string('bottle_name', 100);
            $table->integer('quantity')->default(1);
            $table->integer('unit_price_idr');
            $table->integer('total_price_idr');
            $table->integer('spout_ml_dispensed')->default(750);
            $table->integer('variance_ml')->default(0);
            $table->string('status', 30)->default('served');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_table_bottle_orders');
        Schema::dropIfExists('ven_turnstile_devices');
        Schema::dropIfExists('ven_zone_relocation_logs');
        Schema::dropIfExists('ven_bag_inspections');
        Schema::dropIfExists('ven_crowd_crush_incidents');
    }
};
