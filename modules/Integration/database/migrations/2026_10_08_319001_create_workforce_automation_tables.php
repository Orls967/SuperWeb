<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workforce_automation_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('assessment_code')->unique();
            $table->string('function_name'); // FINANCE_AP_AR, CUSTOMER_SUPPORT, FLEET_DISPATCH
            $table->decimal('automatable_task_pct', 5, 2); // 319.1 e.g. 60.00%
            $table->decimal('human_quality_counter_metric_min_pct', 5, 2)->default(98.00); // 319.6 Quality counter-metric
            $table->decimal('realized_quality_pct', 5, 2)->default(99.00);
            $table->boolean('labor_governance_approved')->default(false); // 319.2 Governance approval
            $table->timestamps();
        });

        Schema::create('workforce_just_transition_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code')->unique();
            $table->string('assessment_code')->index();
            $table->integer('impacted_headcount');
            $table->integer('redeployed_or_reskilled_count')->default(0);
            $table->boolean('just_transition_redeployment_completed')->default(false); // 319.5 Edge case
            $table->boolean('layoff_permitted')->default(false); // 319.5 Layoff strictly prohibited before reskilling
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_just_transition_plans');
        Schema::dropIfExists('workforce_automation_assessments');
    }
};
