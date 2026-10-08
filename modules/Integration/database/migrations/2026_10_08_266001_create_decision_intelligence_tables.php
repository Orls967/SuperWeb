<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_catalog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('decision_code')->unique();
            $table->string('domain_line')->index();
            $table->string('decision_title');
            $table->string('owner_role');
            $table->string('model_used');
            $table->decimal('predicted_outcome_value', 15, 2);
            $table->decimal('actual_outcome_value', 15, 2)->nullable(); // 266.1, 266.6
            $table->boolean('passed_workbench_simulation')->default(true); // 266.5
            $table->boolean('is_governance_exception')->default(false); // 266.5
            $table->text('governance_exception_notes')->nullable();
            $table->string('review_cycle_status')->default('PENDING'); // PENDING, EVALUATED, CLOSED
            $table->timestamps();
        });

        Schema::create('decision_quality_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('decision_id');
            $table->decimal('consistency_score', 5, 2);
            $table->decimal('prediction_accuracy_pct', 5, 2);
            $table->boolean('bias_detected')->default(false); // 266.2
            $table->boolean('manager_training_recommended')->default(false); // 266.2
            $table->timestamps();
        });

        Schema::create('decision_scenario_workbenches', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_code')->unique();
            $table->string('scenario_title');
            $table->boolean('is_sandbox_isolated')->default(true); // 266.4, 266.7
            $table->boolean('production_data_touched')->default(false); // 266.7 strictly false
            $table->json('simulation_input_params_json');
            $table->json('deterministic_outcome_result_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_scenario_workbenches');
        Schema::dropIfExists('decision_quality_scores');
        Schema::dropIfExists('decision_catalog_entries');
    }
};
