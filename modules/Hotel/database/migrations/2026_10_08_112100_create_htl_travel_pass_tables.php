<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 112.1 Travel Pass Global Memberships
        Schema::create('htl_travel_passes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pass_number', 32)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('tier', 16)->default('SILVER'); // SILVER, GOLD, PLATINUM, BLACK
            $table->integer('cumulative_nights')->default(0);
            $table->bigInteger('cumulative_spend_idr')->default(0);
            $table->bigInteger('pts_balance')->default(0);
            $table->timestamps();
        });

        // 112.2 Points Ledger Entries (FIFO tracking & Multi-line spend)
        Schema::create('htl_travel_pass_points_ledger', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('travel_pass_id');
            $table->string('transaction_type', 32); // EARN, REDEEM, EXPIRE, TRANSFER_OUT
            $table->string('originating_line', 32); // HOTEL, VENUE, RESTO, HEALTH, RETAIL
            $table->bigInteger('pts_amount');
            $table->bigInteger('liability_value_idr'); // Value of points (e.g. 1 PTS = 100 IDR)
            $table->string('reference_code', 64);
            $table->timestamps();

            $table->foreign('travel_pass_id')->references('id')->on('htl_travel_passes')->cascadeOnDelete();
        });

        // 112.4 Dynamic Award Room Pricing Matrix
        Schema::create('htl_award_night_pricings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->string('room_type', 32);
            $table->date('stay_date');
            $table->decimal('occupancy_rate_percent', 5, 2);
            $table->bigInteger('base_award_pts');
            $table->bigInteger('dynamic_award_pts');
            $table->bigInteger('minimum_floor_pts');
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('htl_properties')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('htl_award_night_pricings');
        Schema::dropIfExists('htl_travel_pass_points_ledger');
        Schema::dropIfExists('htl_travel_passes');
    }
};
