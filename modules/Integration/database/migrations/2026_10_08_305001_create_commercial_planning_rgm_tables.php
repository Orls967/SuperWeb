<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('promo_code')->unique();
            $table->string('product_line'); // e.g. RETAIL_FMCG, AUTO_PARTS
            $table->decimal('baseline_sales_usd', 15, 2);
            $table->decimal('promotional_sales_usd', 15, 2);
            $table->decimal('incremental_lift_pct', 5, 2);
            $table->decimal('min_margin_guardrail_pct', 5, 2)->default(15.00); // 305.1 & 305.5
            $table->decimal('net_realized_margin_pct', 5, 2);
            $table->boolean('is_halted_by_margin_guard')->default(false); // 305.5 Edge case
            $table->decimal('promo_roi_pct', 6, 2); // 305.2 & 305.4
            $table->timestamps();
        });

        Schema::create('commercial_forecast_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('override_code')->unique();
            $table->string('planner_id');
            $table->decimal('projected_error_reduction_savings_usd', 15, 2);
            $table->decimal('override_cost_finance_usd', 15, 2); // 305.6 Finance-sourced cost
            $table->decimal('value_of_information_net_usd', 15, 2); // 305.3 VOI = savings - cost
            $table->boolean('is_override_authorized')->default(false); // 305.3 Only when VOI > 0
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_forecast_overrides');
        Schema::dropIfExists('commercial_promotions');
    }
};
