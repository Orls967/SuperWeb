<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('human_capital_analytics_cohorts', function (Blueprint $table) {
            $table->id();
            $table->string('cohort_code')->unique();
            $table->string('department_name');
            $table->integer('sample_size_k'); // 325.4 & 325.6 k-anonymity privacy threshold
            $table->decimal('correlation_learning_to_retention', 4, 3); // -1.000 to 1.000 (325.1 correlation labeled)
            $table->boolean('is_suppressed_for_privacy')->default(false); // 325.4 & 325.6
            $table->timestamps();
        });

        Schema::create('human_capital_investment_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decision_code')->unique();
            $table->string('investment_type'); // TRAINING, EXTERNAL_HIRE, AUTOMATION (325.3)
            $table->decimal('cost_usd', 15, 2);
            $table->decimal('projected_benefit_usd', 15, 2);
            $table->decimal('uncertainty_margin_pct', 5, 2)->default(15.00); // 325.3
            $table->boolean('has_measured_empirical_evidence')->default(false); // 325.5 Edge case
            $table->boolean('claim_approved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_capital_investment_decisions');
        Schema::dropIfExists('human_capital_analytics_cohorts');
    }
};
