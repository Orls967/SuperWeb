<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 89.1 Venues, Zones, Tables, Events, Tickets
        Schema::create('ven_entertainment_venues', function (Blueprint $table) {
            $table->id();
            $table->string('venue_code', 32)->unique();
            $table->string('name', 128);
            $table->string('venue_type', 32); // BEACH_CLUB, NIGHTCLUB, ROOFTOP_LOUNGE, FESTIVAL_GROUNDS
            $table->string('city', 64);
            $table->integer('max_legal_capacity');
            $table->integer('min_age_requirement')->default(21);
            $table->timestamps();
        });

        Schema::create('ven_entertainment_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone_code', 32)->unique();
            $table->unsignedBigInteger('venue_id');
            $table->string('name', 64); // POOL_DECK, DANCE_FLOOR, VIP_BALCONY, BEACHFRONT
            $table->integer('capacity_limit');
            $table->integer('current_occupancy')->default(0);
            $table->boolean('crowd_lockdown_active')->default(false);
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('ven_entertainment_venues')->cascadeOnDelete();
        });

        Schema::create('ven_entertainment_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_code', 32)->unique();
            $table->unsignedBigInteger('venue_id');
            $table->string('title', 128);
            $table->dateTime('doors_open_at');
            $table->bigInteger('ticket_floor_price_idr');
            $table->bigInteger('ticket_ceiling_price_idr');
            $table->bigInteger('current_ticket_price_idr');
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('ven_entertainment_venues')->cascadeOnDelete();
        });

        // 89.3 Non-fungible hash-chained tickets with anti-replay
        Schema::create('ven_entertainment_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 32)->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('buyer_user_id');
            $table->bigInteger('price_paid_idr');
            $table->string('ticket_hash', 64);
            $table->string('status', 32)->default('VALID'); // VALID, USED, TRANSFERRED, CANCELLED
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('ven_entertainment_events')->cascadeOnDelete();
        });

        // 89.5 VIP Tables and Bottle Service with Escrow Deposit
        Schema::create('ven_entertainment_table_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 32)->unique();
            $table->unsignedBigInteger('event_id');
            $table->string('table_number', 16);
            $table->unsignedBigInteger('user_id');
            $table->bigInteger('minimum_spend_idr');
            $table->bigInteger('deposit_amount_idr');
            $table->string('status', 32)->default('HELD'); // HELD, SEATED, COMPLETED, NO_SHOW
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('ven_entertainment_events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_entertainment_table_bookings');
        Schema::dropIfExists('ven_entertainment_tickets');
        Schema::dropIfExists('ven_entertainment_events');
        Schema::dropIfExists('ven_entertainment_zones');
        Schema::dropIfExists('ven_entertainment_venues');
    }
};
