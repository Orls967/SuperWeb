<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('candidate_code')->unique();
            $table->string('requisition_code')->index();
            $table->string('full_name');
            $table->decimal('skill_match_score', 5, 2)->default(0);
            $table->boolean('consent_given')->default(false);
            $table->string('background_check_status')->default('PENDING'); // PENDING, PASSED, FAILED
            $table->string('background_check_notes')->nullable();
            $table->string('rehire_eligibility')->default('ELIGIBLE'); // ELIGIBLE, INELIGIBLE_BLACKLIST, ALUMNI_PREFERRED
            $table->string('status')->default('SOURCED'); // SOURCED, SCREENED, INTERVIEWED, OFFERED, ACCEPTED, REJECTED
            $table->timestamps();
        });

        Schema::create('hcm_onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->index();
            $table->string('task_name');
            $table->string('stage'); // PRE_DAY, DAY_1_PROVISIONING, TRAINING_PATH, PROBATION_REVIEW
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hcm_employee_access_provisioning', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->boolean('system_access_active')->default(true);
            $table->json('access_scopes')->nullable();
            $table->timestamp('deprovisioned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hcm_offboarding_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('offboarding_code')->unique();
            $table->string('employee_id')->unique();
            $table->date('resignation_date');
            $table->boolean('knowledge_transferred')->default(false);
            $table->boolean('assets_returned')->default(false);
            $table->boolean('account_deprovisioned')->default(false);
            $table->boolean('final_settlement_paid')->default(false);
            $table->decimal('final_settlement_amount', 15, 2)->default(0);
            $table->string('status')->default('INITIATED'); // INITIATED, IN_PROGRESS, COMPLETED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_offboarding_checklists');
        Schema::dropIfExists('hcm_employee_access_provisioning');
        Schema::dropIfExists('hcm_onboarding_tasks');
        Schema::dropIfExists('hcm_candidates');
    }
};
