<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_market_disruption_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_code')->unique();
            $table->string('disruption_type'); // new_competitor, demand_collapse, technology_shift (469.1)
            $table->decimal('simulated_ebitda_impact_percentage', 5, 2);
            $table->boolean('sandbox_only')->default(true); // 469.3, 469.4
            $table->string('confidence_level')->default('high'); // 469.6 risk: high, medium, low
            $table->boolean('severe_loss_mitigation_plan_logged')->default(false); // 469.5 edge case
            $table->string('strategic_lever_selected'); // pricing, cost, portfolio, partnership (469.2)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_market_disruption_scenarios');
    }
};
