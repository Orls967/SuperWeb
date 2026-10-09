<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 113.1 Travel Platform Bundles & Multi-vendor Escrow
        Schema::create('htl_travel_bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bundle_code', 32)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('destination_city', 64);
            $table->bigInteger('total_price_idr');
            $table->bigInteger('flight_vendor_share_idr');
            $table->bigInteger('hotel_vendor_share_idr');
            $table->bigInteger('transport_vendor_share_idr');
            $table->bigInteger('event_vendor_share_idr');
            $table->string('status', 32)->default('ESCROWED'); // ESCROWED, SETTLED, CANCELLED
            $table->timestamps();
        });

        // 113.2 Itinerary Engine
        Schema::create('htl_itineraries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bundle_id');
            $table->integer('day_number');
            $table->string('activity_name', 128);
            $table->string('venue_type', 32); // HOTEL, RESTO, VENUE, ATTRACTION
            $table->bigInteger('cancellation_penalty_fee_idr')->default(0);
            $table->string('status', 32)->default('BOOKED'); // BOOKED, RESCHEDULED, CANCELLED
            $table->timestamps();

            $table->foreign('bundle_id')->references('id')->on('htl_travel_bundles')->cascadeOnDelete();
        });

        // 113.4 Corporate Travel Desk with Policy Limits
        Schema::create('htl_corporate_travel_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_code', 32)->unique();
            $table->string('corporate_party_id', 64);
            $table->unsignedBigInteger('employee_id');
            $table->string('employee_grade', 32); // EXECUTIVE, MANAGER, STAFF
            $table->bigInteger('policy_budget_limit_idr');
            $table->bigInteger('requested_amount_idr');
            $table->boolean('approved_by_manager')->default(false);
            $table->string('status', 32)->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('htl_corporate_travel_requests');
        Schema::dropIfExists('htl_itineraries');
        Schema::dropIfExists('htl_travel_bundles');
    }
};
