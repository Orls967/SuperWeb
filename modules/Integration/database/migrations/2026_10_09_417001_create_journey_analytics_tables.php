<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_journey_funnels', function (Blueprint $table) {
            $table->id();
            $table->string('funnel_code')->unique();
            $table->string('journey_type'); // book_stay_dine, buy_deliver_return, admit_treat_bill, enroll_learn_credential
            $table->string('cohort_period');
            $table->integer('denominator_starts'); // 417.2
            $table->integer('step1_completions');
            $table->integer('step2_completions');
            $table->integer('step3_completions');
            $table->decimal('conversion_rate', 5, 2);
            $table->integer('metric_version')->default(1); // 417.5 versioned metric
            $table->timestamps();
        });

        Schema::create('crm_journey_experiments', function (Blueprint $table) {
            $table->id();
            $table->string('experiment_code')->unique();
            $table->foreignId('funnel_id')->constrained('crm_journey_funnels')->cascadeOnDelete();
            $table->string('hypothesis');
            $table->decimal('sample_size_reach', 10, 2);
            $table->boolean('statistical_significance_reached')->default(false); // 417.2, 417.4
            $table->boolean('journey_level_regression_checked')->default(false); // 417.6
            $table->boolean('standardized')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_journey_experiments');
        Schema::dropIfExists('crm_journey_funnels');
    }
};
