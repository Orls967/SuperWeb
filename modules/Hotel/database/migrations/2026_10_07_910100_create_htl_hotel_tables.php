<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('htl_properties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('property_code')->unique();
            $table->string('name');
            $table->string('property_type', 32); // CITY_HOTEL, RESORT, VILLA, GLAMPING
            $table->string('city', 64);
            $table->unsignedInteger('total_rooms')->default(100);
            $table->timestamps();
        });

        Schema::create('htl_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->string('room_number', 32);
            $table->string('room_type', 32); // DELUXE, SUITE, PRESIDENTIAL, VILLA
            $table->unsignedBigInteger('base_rate_idr');
            $table->string('status', 32)->default('available'); // available, occupied, maintenance
            $table->string('smart_lock_token')->nullable();
            $table->timestamp('smart_lock_expires_at')->nullable();
            $table->boolean('energy_setback_active')->default(false);
            $table->timestamps();

            $table->unique(['property_id', 'room_number']);
        });

        Schema::create('htl_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->uuid('room_id');
            $table->string('reservation_number')->unique();
            $table->uuid('guest_user_id');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedBigInteger('daily_rate_idr');
            $table->unsignedBigInteger('total_room_charge_idr');
            $table->string('status', 32)->default('confirmed'); // confirmed, checked_in, checked_out, cancelled
            $table->timestamps();
        });

        Schema::create('htl_folios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reservation_id')->unique();
            $table->uuid('guest_user_id');
            $table->unsignedBigInteger('total_room_charges')->default(0);
            $table->unsignedBigInteger('total_addon_charges')->default(0); // F&B, Spa, etc.
            $table->unsignedBigInteger('total_paid')->default(0);
            $table->unsignedBigInteger('outstanding_balance')->default(0);
            $table->string('status', 32)->default('open'); // open, settled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('htl_folios');
        Schema::dropIfExists('htl_reservations');
        Schema::dropIfExists('htl_rooms');
        Schema::dropIfExists('htl_properties');
    }
};
