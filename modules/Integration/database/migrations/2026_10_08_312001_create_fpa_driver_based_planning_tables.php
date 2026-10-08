<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fpa_driver_based_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code')->unique();
            $table->string('cost_center_code')->index();
            $table->integer('driver_traffic');
            $table->decimal('driver_conversion_rate', 5, 4); // e.g. 0.0350
            $table->decimal('driver_average_order_usd', 10, 2);
            $table->decimal('computed_revenue_usd', 18, 2); // 312.1 traffic * conversion * aov
            $table->decimal('actual_revenue_usd', 18, 2)->default(0.00);
            $table->decimal('variance_pct', 6, 2)->default(0.00);
            $table->boolean('reforecast_mandated')->default(false); // 312.5 Edge case
            $table->timestamps();
        });

        Schema::create('fpa_zero_based_budget_carveouts', function (Blueprint $table) {
            $table->id();
            $table->string('initiative_code')->unique();
            $table->string('cost_center_code');
            $table->decimal('baseline_zero_justification_usd', 18, 2);
            $table->decimal('eliminated_cost_savings_usd', 18, 2); // 312.3 ZBB savings
            $table->boolean('is_strategic_carveout_approved_by_board')->default(false); // 312.6 Board carve-out
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fpa_zero_based_budget_carveouts');
        Schema::dropIfExists('fpa_driver_based_plans');
    }
};
