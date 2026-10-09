<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_ethics_maturity', function (Blueprint $table) {
            $table->id();
            $table->string('cycle_code')->unique(); // e.g. ETHICS-2026-H1
            $table->decimal('culture_survey_score', 5, 2);
            $table->decimal('speak_up_health_score', 5, 2);
            $table->decimal('case_quality_score', 5, 2);
            $table->decimal('composite_ethics_maturity_score', 5, 2); // 481.2
            $table->boolean('requires_mandatory_improvement_plan')->default(false); // 481.5 edge case
            $table->boolean('improvement_plan_submitted')->default(false);
            $table->timestamps();
        });

        Schema::create('int_enterprise_social_license_indexes', function (Blueprint $table) {
            $table->id();
            $table->string('index_code')->unique();
            $table->decimal('community_trust_score', 5, 2);
            $table->decimal('regulatory_standing_score', 5, 2);
            $table->decimal('partner_confidence_score', 5, 2);
            $table->decimal('employee_pride_score', 5, 2);
            $table->decimal('composite_social_license_index', 5, 2); // 481.3
            $table->boolean('open_empirical_methodology_documented')->default(true); // 481.6 risk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_enterprise_social_license_indexes');
        Schema::dropIfExists('int_enterprise_ethics_maturity');
    }
};
