<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_code')->unique();
            $table->string('employee_id')->index();
            $table->string('cycle_period')->index();
            $table->decimal('initial_rating', 3, 2);
            $table->decimal('calibrated_rating', 3, 2)->nullable();
            $table->text('calibration_justification')->nullable();
            $table->string('calibrated_by')->nullable();
            $table->decimal('bonus_multiplier', 4, 2)->default(1.00);
            $table->string('status')->default('SUBMITTED'); // SUBMITTED, CALIBRATED, FINALIZED
            $table->timestamps();
        });

        Schema::create('hcm_engagement_surveys', function (Blueprint $table) {
            $table->id();
            $table->string('survey_code')->unique();
            $table->string('title');
            $table->string('cycle_period')->index();
            $table->integer('min_anonymity_threshold')->default(5);
            $table->timestamps();
        });

        Schema::create('hcm_engagement_responses', function (Blueprint $table) {
            $table->id();
            $table->string('survey_code')->index();
            $table->string('team_cohort')->index();
            $table->decimal('score', 3, 2);
            $table->string('driver_category'); // CULTURE, COMPENSATION, LEADERSHIP, WORKLOAD
            $table->timestamps();
        });

        Schema::create('hcm_people_analytics_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->index();
            $table->string('period')->index();
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->integer('tenure_months')->default(0);
            $table->decimal('last_rating', 3, 2)->default(3.00);
            $table->decimal('predicted_attrition_risk', 5, 2)->default(0);
            $table->boolean('retention_outreach_triggered')->default(false);
            $table->timestamps();
        });

        Schema::create('hcm_manager_effectiveness_scores', function (Blueprint $table) {
            $table->id();
            $table->string('manager_id')->unique();
            $table->decimal('delivery_score', 5, 2);
            $table->decimal('engagement_score', 5, 2);
            $table->decimal('growth_score', 5, 2);
            $table->decimal('composite_score', 5, 2);
            $table->string('promotion_recommendation'); // RECOMMENDED, DEVELOPMENT_NEEDED, NOT_READY
            $table->text('documented_judgment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_manager_effectiveness_scores');
        Schema::dropIfExists('hcm_people_analytics_metrics');
        Schema::dropIfExists('hcm_engagement_responses');
        Schema::dropIfExists('hcm_engagement_surveys');
        Schema::dropIfExists('hcm_performance_reviews');
    }
};
