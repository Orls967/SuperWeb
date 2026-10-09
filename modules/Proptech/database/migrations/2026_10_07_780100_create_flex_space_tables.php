<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 78.1 Flex-space inventory
        Schema::create('prp_flex_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('space_code', 32)->unique();
            $table->string('name', 128);
            $table->string('space_type', 32); // MEETING_ROOM, BOOTH, COWORKING_DESK, STUDIO
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->integer('capacity_persons');
            $table->bigInteger('rate_per_hour_idr');
            $table->decimal('area_sqm', 8, 2)->default(20.0);
            $table->string('status', 32)->default('AVAILABLE'); // AVAILABLE, MAINTENANCE
            $table->timestamps();

            $table->foreign('zone_id')->references('id')->on('prp_building_zones')->nullOnDelete();
        });

        // 78.2 Time-locked bookings with deposit & anti-overlap
        Schema::create('prp_flex_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 32)->unique();
            $table->unsignedBigInteger('flex_space_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->bigInteger('deposit_amount_idr');
            $table->bigInteger('total_price_idr');
            $table->string('status', 32)->default('HELD'); // HELD, CONFIRMED, CHECKED_IN, COMPLETED, NO_SHOW, CANCELLED
            $table->string('checked_in_passport_hash', 64)->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->timestamps();

            $table->foreign('flex_space_id')->references('id')->on('prp_flex_spaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prp_flex_bookings');
        Schema::dropIfExists('prp_flex_spaces');
    }
};
