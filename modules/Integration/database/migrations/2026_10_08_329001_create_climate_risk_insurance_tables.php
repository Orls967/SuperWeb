<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('climate_insurance_resilience_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_code')->unique();
            $table->string('site_code');
            $table->decimal('base_premium_usd', 15, 2);
            $table->boolean('has_verified_adaptation_measure')->default(false);
            $table->decimal('risk_mitigation_credit_usd', 15, 2)->default(0.00); // 329.1 & 329.4
            $table->decimal('final_billed_premium_usd', 15, 2);
            $table->timestamps();
        });

        Schema::create('parametric_climate_sensor_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('event_code')->unique();
            $table->string('site_code');
            $table->string('sensor_feed_status'); // ONLINE, OUTAGE_OFFLINE
            $table->boolean('is_manual_fallback_activated')->default(false); // 329.3 & 329.5 Edge case
            $table->boolean('has_official_meteorological_data')->default(false);
            $table->decimal('measured_wind_or_flood_value', 8, 2)->nullable();
            $table->boolean('parametric_payout_triggered')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametric_climate_sensor_triggers');
        Schema::dropIfExists('climate_insurance_resilience_policies');
    }
};
