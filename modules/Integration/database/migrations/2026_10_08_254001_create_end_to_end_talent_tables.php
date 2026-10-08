<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_unified_employee_journeys', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->string('employee_name');
            $table->string('current_stage'); // ONBOARDING, DEVELOPING, DEPLOYED, PERFORMING, REWARDED, TRANSITIONING, EXITED
            $table->string('next_action_required'); // 254.7
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('talent_requisitions_internal_first', function (Blueprint $table) {
            $table->id();
            $table->string('req_code')->unique();
            $table->string('target_role');
            $table->json('skill_requirements_json');
            $table->boolean('internal_marketplace_offered')->default(true); // 254.2, 254.5
            $table->integer('internal_offer_days')->default(14);
            $table->boolean('external_candidate_considered')->default(false);
            $table->boolean('fair_process_documented')->default(false);
            $table->timestamps();
        });

        Schema::create('talent_automation_workforce_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_code')->unique();
            $table->string('department');
            $table->decimal('automation_impact_pct', 5, 2);
            $table->boolean('reskilling_plan_triggered')->default(false); // 254.3, 254.6
            $table->integer('projected_headcount_delta');
            $table->decimal('cost_trajectory_usd', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_automation_workforce_scenarios');
        Schema::dropIfExists('talent_requisitions_internal_first');
        Schema::dropIfExists('talent_unified_employee_journeys');
    }
};
