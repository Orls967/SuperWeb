<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 117.1 Face-ID / Biometric Entry Template Hash (Zero-knowledge proof hash)
        Schema::create('ven_biometric_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('credential_code', 32)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('biometric_template_hash', 64)->unique(); // Irreversible cryptographic template hash
            $table->string('liveness_signature_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 117.2 & 117.3 Real-time Zone Density & Crowd Safety Alerts
        Schema::create('ven_crowd_safety_telemetries', function (Blueprint $table) {
            $table->id();
            $table->string('telemetry_code', 32)->unique();
            $table->unsignedBigInteger('zone_id');
            $table->integer('current_headcount');
            $table->integer('density_capacity_limit');
            $table->decimal('occupancy_percentage', 5, 2);
            $table->integer('predicted_headcount_15m');
            $table->string('recommended_action', 64)->default('NORMAL_ENTRY'); // NORMAL_ENTRY, SLOW_ENTRY, OPEN_SECONDARY_GATE, RESTRICT_ACCESS
            $table->boolean('gate_restricted')->default(false);
            $table->timestamps();

            $table->foreign('zone_id')->references('id')->on('ven_entertainment_zones')->cascadeOnDelete();
        });

        // 117.5 Personalized In-Venue Promotion Dispatches (Deduplicated)
        Schema::create('ven_personalized_promos', function (Blueprint $table) {
            $table->id();
            $table->string('promo_dispatch_code', 32)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('zone_id');
            $table->string('promo_title', 128);
            $table->integer('discount_percent');
            $table->dateTime('expires_at');
            $table->string('status', 32)->default('DELIVERED');
            $table->timestamps();

            $table->unique(['user_id', 'zone_id', 'promo_title']); // Rule 117.6 (d): promo ganda tidak dikirim dobel
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_personalized_promos');
        Schema::dropIfExists('ven_crowd_safety_telemetries');
        Schema::dropIfExists('ven_biometric_credentials');
    }
};
