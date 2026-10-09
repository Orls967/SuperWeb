<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 206.1: BIA tiering per critical line with measured RTO targets
        Schema::create('erm_bia_continuity_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code')->unique();
            $table->string('domain_code');
            $table->string('continuity_tier'); // TIER_1_LIFE_SAFETY, TIER_2_FINANCIAL_OPS, TIER_3_STANDARD
            $table->integer('target_rto_minutes');
            $table->integer('actual_drill_rto_minutes')->default(0);
            $table->boolean('rto_met')->default(true);
            $table->timestamps();
        });

        // 206.3: Crisis Command Center Virtual War Room & Public Statements
        Schema::create('erm_crisis_war_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_code')->unique();
            $table->string('crisis_scenario');
            $table->string('status')->default('ACTIVE'); // ACTIVE, STANDDOWN
            $table->text('public_holding_statement')->nullable();
            $table->string('statement_approved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erm_crisis_war_rooms');
        Schema::dropIfExists('erm_bia_continuity_plans');
    }
};
