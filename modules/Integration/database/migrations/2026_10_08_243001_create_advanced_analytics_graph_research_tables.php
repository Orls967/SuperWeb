<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_graph_edges', function (Blueprint $table) {
            $table->id();
            $table->string('source_node')->index();
            $table->string('target_node')->index();
            $table->string('relationship_type'); // SUPPLY_CHAIN, OWNERSHIP, PAYMENT_FLOW, DISTRIBUTION
            $table->decimal('weight', 15, 2)->default(1.00);
            $table->boolean('is_suspicious_flow')->default(false); // 243.1
            $table->timestamps();
        });

        Schema::create('analytics_monte_carlo_simulations', function (Blueprint $table) {
            $table->id();
            $table->string('sim_code')->unique();
            $table->string('scenario_type'); // DEMAND_SHOCK, WEATHER_DISRUPTION, GRID_OUTAGE
            $table->integer('random_seed'); // 243.5 deterministic
            $table->integer('iterations_count');
            $table->decimal('var_95_loss_estimate', 15, 2);
            $table->boolean('is_extreme_outlier')->default(false);
            $table->decimal('clamped_decision_limit', 15, 2); // 243.6 sanity check clamp
            $table->timestamps();
        });

        Schema::create('analytics_prescriptive_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('rec_code')->unique();
            $table->string('domain_line');
            $table->string('recommendation_title');
            $table->decimal('expected_impact_usd', 15, 2);
            $table->decimal('actual_impact_usd', 15, 2)->nullable(); // 243.3
            $table->boolean('is_production_promoted')->default(false);
            $table->boolean('ai_governance_reviewed')->default(false); // 243.7
            $table->timestamps();
        });

        Schema::create('analytics_research_experiments', function (Blueprint $table) {
            $table->id();
            $table->string('experiment_code')->unique();
            $table->string('title');
            $table->boolean('uses_sensitive_data')->default(false);
            $table->boolean('ethical_checklist_completed')->default(false);
            $table->boolean('irb_ethical_approved')->default(false); // 243.4
            $table->string('approved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_research_experiments');
        Schema::dropIfExists('analytics_prescriptive_recommendations');
        Schema::dropIfExists('analytics_monte_carlo_simulations');
        Schema::dropIfExists('analytics_graph_edges');
    }
};
