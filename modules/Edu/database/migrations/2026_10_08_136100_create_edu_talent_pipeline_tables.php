<?php

declare(strict_types=1);

namespace Modules\Edu\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 136.1 Talent profiles & job vacancies across lines
        Schema::create('edu_talent_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('candidate_id')->unique();
            $table->string('full_name');
            $table->string('city');
            $table->json('skills'); // e.g. ["EV_HV_TECH", "HSE_K3", "JAVA"]
            $table->bigInteger('salary_expectation_minor');
            $table->integer('years_experience')->default(1);
            $table->string('status')->default('AVAILABLE');
            $table->timestamps();

            $table->index(['city', 'status']);
        });

        Schema::create('edu_job_openings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('job_code')->unique();
            $table->string('title');
            $table->string('hiring_entity_id');
            $table->string('location_city');
            $table->json('required_skills');
            $table->bigInteger('salary_budget_minor');
            $table->string('job_type'); // FULL_TIME, EPC_CONTRACT, GIG_SHIFT
            $table->string('status')->default('OPEN');
            $table->timestamps();

            $table->index(['hiring_entity_id', 'status']);
        });

        // 136.3 Headhunter agency fees & warranty hold
        Schema::create('edu_headhunter_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('placement_code')->unique();
            $table->string('headhunter_agency_id');
            $table->string('candidate_id');
            $table->string('hiring_entity_id');
            $table->bigInteger('candidate_first_month_salary_minor');
            $table->double('fee_percentage', 5, 2)->default(20.0); // e.g. 20%
            $table->bigInteger('fee_amount_minor');
            $table->integer('warranty_days')->default(90); // 90 days probation warranty
            $table->date('hired_date');
            $table->date('warranty_ends_date');
            $table->string('payout_status')->default('HOLD'); // HOLD, RELEASED, CLAWBACK
            $table->timestamps();

            $table->index(['headhunter_agency_id', 'payout_status'], 'edu_hh_contracts_agency_stat_idx');
        });

        // 136.4 Contingent workforce timesheet & contracts
        Schema::create('edu_contingent_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_code')->unique();
            $table->string('worker_id');
            $table->string('client_entity_id');
            $table->string('project_code');
            $table->integer('contract_max_hours');
            $table->integer('hours_rendered')->default(0);
            $table->bigInteger('hourly_rate_minor');
            $table->date('valid_until');
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->index(['worker_id', 'project_code']);
        });

        // 136.5 Internal Mobility & transfer tracking
        Schema::create('edu_internal_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transfer_code')->unique();
            $table->string('employee_id');
            $table->string('from_entity_id');
            $table->string('to_entity_id');
            $table->date('effective_date');
            $table->bigInteger('base_salary_minor');
            $table->boolean('payroll_processed')->default(false);
            $table->string('status')->default('APPROVED'); // APPROVED, COMPLETED
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_internal_transfers');
        Schema::dropIfExists('edu_contingent_contracts');
        Schema::dropIfExists('edu_headhunter_contracts');
        Schema::dropIfExists('edu_job_openings');
        Schema::dropIfExists('edu_talent_profiles');
    }
};
