<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_compensation_bands', function (Blueprint $table) {
            $table->id();
            $table->string('band_code')->unique();
            $table->string('job_family');
            $table->string('grade_level');
            $table->decimal('min_salary', 18, 2);
            $table->decimal('mid_salary', 18, 2);
            $table->decimal('max_salary', 18, 2);
            $table->string('benchmark_provider')->default('MERCER_SIMULATED'); // 422.1
            $table->timestamps();
        });

        Schema::create('hcm_merit_pay_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_code')->unique();
            $table->string('employee_id');
            $table->string('band_code');
            $table->decimal('current_salary', 18, 2);
            $table->decimal('proposed_salary', 18, 2);
            $table->decimal('merit_increase_percent', 5, 2);
            $table->boolean('within_merit_budget')->default(true); // 422.2, 422.4
            $table->boolean('within_salary_band')->default(true); // 422.4
            $table->boolean('equity_gap_flagged')->default(false); // 422.3 unexplained gap
            $table->boolean('equity_remediation_completed')->default(true); // 422.6
            $table->string('calibration_committee_approval')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_merit_pay_reviews');
        Schema::dropIfExists('hcm_compensation_bands');
    }
};
