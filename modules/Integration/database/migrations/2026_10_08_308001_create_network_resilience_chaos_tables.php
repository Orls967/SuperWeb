<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_redundancy_dependencies', function (Blueprint $table) {
            $table->id();
            $table->string('dependency_code')->unique();
            $table->string('critical_component'); // CLOUD_REGION_PRIMARY, SUPPLIER_TIER_1_SEMICONDUCTOR, POWER_GRID_SUBSTATION
            $table->boolean('has_n_minus_one_failover')->default(true); // 308.1 N-1 analysis
            $table->decimal('redundancy_investment_cost_usd', 15, 2);
            $table->boolean('trade_off_cost_approved')->default(true); // 308.6 Tradeoff evaluated & approved
            $table->timestamps();
        });

        Schema::create('network_chaos_game_days', function (Blueprint $table) {
            $table->id();
            $table->string('exercise_code')->unique();
            $table->string('fault_injection_type'); // NODE_KILL, REGION_BLACKOUT, VENDOR_COMM_LOSS
            $table->integer('max_allowed_rto_seconds')->default(60); // 308.3 & 308.4 Recovery Time Objective
            $table->integer('actual_recovery_seconds');
            $table->boolean('rto_target_met')->default(true); // 308.4 & 308.5
            $table->boolean('is_blocker_finding_logged')->default(false); // 308.5 Edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_chaos_game_days');
        Schema::dropIfExists('network_redundancy_dependencies');
    }
};
