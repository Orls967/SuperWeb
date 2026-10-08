<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_pay_structures', function (Blueprint $table) {
            $table->id();
            $table->string('grade_band');
            $table->string('country_code', 8);
            $table->string('currency', 8)->default('IDR');
            $table->decimal('min_salary', 15, 2);
            $table->decimal('mid_salary', 15, 2);
            $table->decimal('max_salary', 15, 2);
            $table->timestamps();
            $table->unique(['grade_band', 'country_code']);
        });

        Schema::create('hcm_employee_compensations', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->index();
            $table->string('grade_band');
            $table->string('country_code', 8);
            $table->string('currency', 8)->default('IDR');
            $table->decimal('base_salary', 15, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        Schema::create('hcm_variable_pay_pools', function (Blueprint $table) {
            $table->id();
            $table->string('pool_code')->unique();
            $table->decimal('total_pool_amount', 15, 2);
            $table->decimal('distributed_amount', 15, 2)->default(0);
            $table->string('status')->default('OPEN'); // OPEN, DISTRIBUTED, RECONCILED
            $table->timestamps();
        });

        Schema::create('hcm_variable_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_code')->unique();
            $table->string('pool_code')->index();
            $table->string('employee_id')->index();
            $table->decimal('payout_amount', 15, 2);
            $table->boolean('is_clawbacked')->default(false);
            $table->string('clawback_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('hcm_benefit_enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('enrollment_code')->unique();
            $table->string('employee_id')->index();
            $table->string('benefit_type'); // HEALTH_INSURANCE, LIFE_INSURANCE, PENSION, WELLNESS, FLEXIBLE
            $table->decimal('monthly_premium', 15, 2);
            $table->string('status')->default('ACTIVE'); // ACTIVE, CANCELLED
            $table->date('enrolled_at');
            $table->date('terminated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hcm_retroactive_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_code')->unique();
            $table->string('employee_id')->index();
            $table->string('target_period'); // e.g. 2026-08
            $table->decimal('adjustment_amount', 15, 2);
            $table->string('reason');
            $table->string('approved_by')->nullable();
            $table->string('status')->default('PENDING'); // PENDING, APPROVED
            $table->timestamps();
        });

        Schema::create('hcm_pay_equity_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_code')->unique();
            $table->string('cohort_group');
            $table->decimal('unadjusted_gap_pct', 5, 2);
            $table->decimal('adjusted_gap_pct', 5, 2);
            $table->text('remediation_plan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_pay_equity_audits');
        Schema::dropIfExists('hcm_retroactive_adjustments');
        Schema::dropIfExists('hcm_benefit_enrollments');
        Schema::dropIfExists('hcm_variable_payouts');
        Schema::dropIfExists('hcm_variable_pay_pools');
        Schema::dropIfExists('hcm_employee_compensations');
        Schema::dropIfExists('hcm_pay_structures');
    }
};
