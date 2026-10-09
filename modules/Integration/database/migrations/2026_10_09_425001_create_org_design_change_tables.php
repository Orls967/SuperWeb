<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_org_design_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_code')->unique();
            $table->string('title');
            $table->integer('span_of_control_ratio'); // e.g. 1 manager : 7 reports
            $table->decimal('total_projected_cost', 18, 2);
            $table->decimal('readiness_score', 5, 2); // 425.2
            $table->boolean('consultation_gate_passed')->default(false); // 425.3, 425.4
            $table->boolean('continuity_coverage_verified')->default(true); // 425.5 edge case
            $table->string('status')->default('draft'); // draft, approved, executed
            $table->timestamps();
        });

        Schema::create('hcm_restructuring_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('hcm_org_design_scenarios')->cascadeOnDelete();
            $table->string('position_code')->unique();
            $table->string('employee_id');
            $table->string('action_type'); // redeploy, freeze, unfreeze, severance
            $table->decimal('severance_amount', 18, 2)->default(0.00);
            $table->boolean('is_encumbrance_budget_cleared')->default(true); // 425.6
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_restructuring_positions');
        Schema::dropIfExists('hcm_org_design_scenarios');
    }
};
