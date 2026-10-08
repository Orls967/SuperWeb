<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_network_optimizations', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code')->unique();
            $table->string('planning_month'); // e.g. 2026-11
            $table->decimal('total_projected_cost_usd', 15, 2);
            $table->decimal('target_service_level_pct', 5, 2)->default(98.50);
            $table->decimal('simulated_service_level_pct', 5, 2);
            $table->boolean('service_level_guardrail_met')->default(true); // 301.6
            $table->boolean('is_sandbox_simulation')->default(true); // 301.4 Sandbox does not alter real stock
            $table->timestamps();
        });

        Schema::create('supply_multi_echelon_inventory_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_code')->unique();
            $table->string('sku')->index();
            $table->string('echelon_level'); // CENTRAL_DC, REGIONAL_HUB, LOCAL_STORE
            $table->integer('safety_stock_units');
            $table->integer('reorder_point_units');
            $table->decimal('allocated_working_capital_usd', 15, 2);
            $table->decimal('min_guaranteed_service_level_pct', 5, 2)->default(95.00); // 301.2 & 301.6
            $table->timestamps();
        });

        Schema::create('supply_demand_shaping_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code')->unique();
            $table->string('sku')->index();
            $table->string('scarcity_allocation_strategy'); // FAIR_SHARE_TIERED, HISTORICAL_VELOCITY (301.3 & 301.5)
            $table->decimal('dynamic_price_multiplier', 4, 2)->default(1.00);
            $table->boolean('anti_hoarding_quota_enforced')->default(true); // 301.3 & 301.5
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_demand_shaping_rules');
        Schema::dropIfExists('supply_multi_echelon_inventory_policies');
        Schema::dropIfExists('supply_network_optimizations');
    }
};
