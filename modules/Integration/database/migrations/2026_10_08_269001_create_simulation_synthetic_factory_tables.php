<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_synthetic_datasets', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_code')->unique();
            $table->string('domain_line')->index();
            $table->integer('seed_value');
            $table->integer('record_count');
            $table->decimal('reidentification_risk_score', 4, 3)->default(0.002); // 269.4 < 0.01
            $table->boolean('distribution_drift_detected')->default(false); // 269.5
            $table->decimal('drift_p_value', 5, 4)->default(0.5000);
            $table->boolean('privacy_check_passed')->default(true);
            $table->timestamps();
        });

        Schema::create('simulation_marketplace_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code')->unique();
            $table->string('simulator_type'); // DEMAND, GRID, PORT, MINE, HEALTH
            $table->string('requestor_team');
            $table->decimal('cost_per_run_usd', 10, 2); // 269.2, 269.7
            $table->boolean('is_sandbox_isolated')->default(true);
            $table->boolean('wrote_production_data')->default(false); // 269.4
            $table->json('results_summary_json');
            $table->timestamps();
        });

        Schema::create('simulation_counterfactual_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_code')->unique();
            $table->decimal('baseline_price_usd', 10, 2);
            $table->decimal('counterfactual_price_delta_pct', 5, 2);
            $table->integer('seed_value');
            $table->string('deterministic_fingerprint'); // 269.6
            $table->decimal('projected_ebitda_impact_usd', 15, 2);
            $table->text('recommendation_text');
            $table->boolean('is_approved_for_implementation')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_counterfactual_scenarios');
        Schema::dropIfExists('simulation_marketplace_runs');
        Schema::dropIfExists('simulation_synthetic_datasets');
    }
};
