<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 108.1 Medical Tourism Packages & Multi-Vendor Escrow
        Schema::create('hsp_tourism_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_code', 32)->unique();
            $table->unsignedBigInteger('patient_id');
            $table->string('package_name', 128);
            $table->bigInteger('total_price_idr');
            $table->bigInteger('hospital_share_idr');
            $table->bigInteger('hotel_share_idr');
            $table->bigInteger('transport_share_idr');
            $table->string('current_milestone', 32)->default('BOOKED'); // BOOKED, CHECKED_IN_HOSPITAL, PROCEDURE_COMPLETED, SETTLED
            $table->string('status', 32)->default('ESCROWED'); // ESCROWED, IN_PROGRESS, COMPLETED, REFUNDED
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
        });

        // 108.3 Health Membership
        Schema::create('hsp_memberships', function (Blueprint $table) {
            $table->id();
            $table->string('membership_number', 32)->unique();
            $table->unsignedBigInteger('patient_id');
            $table->string('tier', 32)->default('GOLD'); // SILVER, GOLD, PLATINUM
            $table->integer('annual_wellness_credits_pts')->default(1000);
            $table->integer('remaining_credits_pts')->default(1000);
            $table->date('expires_at');
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
        });

        // 108.4 Wearable IoT Adherence & Wellness Rewards
        Schema::create('hsp_wearable_adherences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->date('record_date');
            $table->integer('daily_steps');
            $table->integer('sleep_hours');
            $table->decimal('adherence_score', 4, 2);
            $table->integer('pts_rewarded')->default(0);
            $table->string('proof_hash', 64);
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_wearable_adherences');
        Schema::dropIfExists('hsp_memberships');
        Schema::dropIfExists('hsp_tourism_packages');
    }
};
