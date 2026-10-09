<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_industry_vertical_participants', function (Blueprint $table) {
            $table->id();
            $table->string('partner_code')->unique();
            $table->string('industry_vertical'); // MINING_HEAVY_EQUIPMENT, SMELTER_REFINERY, LOGISTICS_CONTRACTOR
            $table->string('kyb_tier'); // TIER_1_ENTERPRISE, TIER_2_MID, TIER_3_SME
            $table->decimal('platform_fee_rate_pct', 5, 2); // 316.6 Fee tiering
            $table->decimal('reputation_trust_index', 4, 2)->default(9.50); // 316.3 0 - 10 scale
            $table->boolean('remediation_plan_active')->default(false); // 316.5 Edge case
            $table->timestamps();
        });

        Schema::create('b2b_marketplace_liquidity_subsidies', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code')->unique();
            $table->string('industry_vertical');
            $table->decimal('allocated_budget_usd', 15, 2);
            $table->decimal('utilized_subsidy_usd', 15, 2)->default(0.00); // 316.2 & 316.4 Bounded subsidy
            $table->boolean('budget_cap_exceeded')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_marketplace_liquidity_subsidies');
        Schema::dropIfExists('b2b_industry_vertical_participants');
    }
};
