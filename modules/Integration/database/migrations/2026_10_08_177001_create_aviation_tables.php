<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 177.1 & 177.4: Aircraft registry with airworthiness status
        Schema::create('avi_aircrafts', function (Blueprint $table) {
            $table->id();
            $table->string('tail_number')->unique();
            $table->string('model_type');
            $table->integer('max_passenger_capacity');
            $table->decimal('max_cargo_capacity_kg', 18, 2);
            $table->date('airworthiness_certificate_expiry');
            $table->boolean('is_grounded')->default(false);
            $table->timestamps();
        });

        // 177.2: Flight schedules & bookings
        Schema::create('avi_flight_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();
            $table->string('tail_number');
            $table->string('flight_number');
            $table->integer('passenger_seats_booked');
            $table->decimal('fare_paid_idr', 18, 2);
            $table->boolean('is_refunded')->default(false);
            $table->decimal('refund_amount_idr', 18, 2)->default(0.00);
            $table->string('status')->default('CONFIRMED'); // CONFIRMED, REFUNDED
            $table->timestamps();
        });

        // 177.3: Air cargo manifests & custody
        Schema::create('avi_cargo_manifests', function (Blueprint $table) {
            $table->id();
            $table->string('airway_bill_code')->unique();
            $table->string('tail_number');
            $table->decimal('weight_kg', 18, 2);
            $table->boolean('is_dangerous_goods')->default(false);
            $table->boolean('dg_certified')->default(true);
            $table->string('custody_hash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avi_cargo_manifests');
        Schema::dropIfExists('avi_flight_bookings');
        Schema::dropIfExists('avi_aircrafts');
    }
};
