<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprise_policy_simulation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('simulation_code')->unique();
            $table->string('policy_rule_name');
            $table->integer('historical_tx_sample_count');
            $table->integer('simulated_blocked_tx_count');
            $table->decimal('simulated_operational_disruption_pct', 5, 2);
            $table->boolean('mutation_detected_on_seed_data')->default(false); // 336.4 Simulation must not mutate data
            $table->boolean('requires_pre_activation_revision')->default(false); // 336.5 Edge case
            $table->boolean('ready_for_activation')->default(false);
            $table->timestamps();
        });

        Schema::create('enterprise_policy_regression_suites', function (Blueprint $table) {
            $table->id();
            $table->string('suite_code')->unique();
            $table->string('policy_rule_name');
            $table->boolean('behavioral_drift_detected')->default(false); // 336.2
            $table->boolean('change_ticket_mandated')->default(false);
            $table->boolean('regression_gate_passed')->default(true); // 336.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enterprise_policy_regression_suites');
        Schema::dropIfExists('enterprise_policy_simulation_runs');
    }
};
