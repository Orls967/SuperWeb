<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_cold_chain_excursions', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_code')->unique();
            $table->string('cargo_type'); // VACCINE, BIOLOGIC_INSULIN, FRESH_SALMON
            $table->decimal('min_allowed_temp_c', 4, 1)->default(2.0);
            $table->decimal('max_allowed_temp_c', 4, 1)->default(8.0);
            $table->decimal('recorded_temp_c', 4, 1);
            $table->boolean('is_excursion_detected')->default(false); // 303.1 & 303.4
            $table->string('disposition_status')->default('NORMAL_TRANSIT'); // NORMAL_TRANSIT, QUARANTINED (303.5)
            $table->boolean('insurance_claim_triggered')->default(false); // 303.5
            $table->timestamps();
        });

        Schema::create('logistics_high_value_transits', function (Blueprint $table) {
            $table->id();
            $table->string('consignment_code')->unique();
            $table->decimal('declared_value_usd', 15, 2);
            $table->string('route_risk_level'); // LOW, ELEVATED, HIGH_RISK_ZONE
            $table->boolean('route_risk_reassessment_completed')->default(false); // 303.6
            $table->string('primary_custody_agent_id');
            $table->string('secondary_custody_agent_id')->nullable(); // 303.3 & 303.4 Dual control
            $table->boolean('dual_control_verified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_high_value_transits');
        Schema::dropIfExists('logistics_cold_chain_excursions');
    }
};
