<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_twin_fidelity_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_code')->unique();
            $table->string('domain_system'); // HVAC_ENERGY_PLANT, DATA_CENTER_COOLING, ROBOTIC_ASSEMBLY
            $table->decimal('fidelity_score_pct', 5, 2); // 307.3 Fidelity threshold
            $table->decimal('min_required_fidelity_pct', 5, 2)->default(90.00); // 307.4 Gate
            $table->boolean('drift_alert_triggered')->default(false); // 307.3 & 307.4
            $table->timestamps();
        });

        Schema::create('digital_twin_control_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_code')->unique();
            $table->string('model_code')->index();
            $table->string('recommended_action'); // ADJUST_HVAC_SETPOINT, RESCHEDULE_SPINDLE_MAINTENANCE
            $table->decimal('model_fidelity_at_recommendation', 5, 2);
            $table->boolean('auto_execution_applied')->default(false); // 307.1 & 307.5
            $table->string('execution_status')->default('PROPOSED'); // APPLIED, ADVISORY_ONLY (307.5)
            $table->decimal('damping_factor_pct', 5, 2)->default(20.00); // 307.6 Damping & cycle change cap
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_twin_control_actions');
        Schema::dropIfExists('digital_twin_fidelity_models');
    }
};
