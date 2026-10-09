<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdm_culture_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('assessment_code')->unique();
            $table->string('employee_id')->index();
            $table->decimal('values_alignment_score', 5, 2);
            $table->decimal('collaboration_score', 5, 2);
            $table->decimal('composite_culture_score', 5, 2);
            $table->boolean('is_auto_decision_prohibited')->default(true); // 284.4 & 284.6 Human decides
            $table->string('promotion_decision')->default('PENDING_HUMAN_REVIEW'); // PENDING_HUMAN_REVIEW, APPROVED, REJECTED
            $table->string('human_reviewer_id')->nullable(); // 284.6
            $table->timestamps();
        });

        Schema::create('sdm_ona_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('cluster_code')->unique();
            $table->string('department_name');
            $table->integer('sample_size_n');
            $table->integer('anonymity_threshold_min_n')->default(10); // 284.4 & 284.5
            $table->decimal('silo_index_score', 4, 2); // 0 to 1
            $table->boolean('is_data_withheld')->default(false); // 284.5 Withheld if n < threshold
            $table->boolean('interlock_intervention_triggered')->default(false);
            $table->timestamps();
        });

        Schema::create('sdm_dei_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_code')->unique();
            $table->string('reporting_period'); // e.g. 2026-FY
            $table->decimal('female_leadership_pct', 5, 2);
            $table->decimal('regional_talent_representation_pct', 5, 2);
            $table->boolean('governance_report_submitted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdm_dei_metrics');
        Schema::dropIfExists('sdm_ona_metrics');
        Schema::dropIfExists('sdm_culture_assessments');
    }
};
