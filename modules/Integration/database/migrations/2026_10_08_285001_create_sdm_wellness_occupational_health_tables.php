<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdm_occupational_health_surveillances', function (Blueprint $table) {
            $table->id();
            $table->string('surveillance_code')->unique();
            $table->string('worker_id')->index();
            $table->string('high_risk_environment'); // MINING_UNDERGROUND, SMELTER_FURNACE, RADIOLOGY_HOSPITAL
            $table->text('encrypted_medical_vault_token'); // 285.1 Encrypted medical record
            $table->string('fitness_for_duty_status'); // FIT, FIT_WITH_RESTRICTIONS, TEMPORARILY_UNFIT, REFUSED_EXAM
            $table->boolean('medical_details_exposed_to_hr')->default(false); // 285.1 & 285.4 Strict segregation
            $table->boolean('refused_examination')->default(false); // 285.5
            $table->timestamps();
        });

        Schema::create('sdm_eap_counseling_cases', function (Blueprint $table) {
            $table->id();
            $table->string('session_anon_token')->unique(); // 285.2 & 285.6 Anonymized token
            $table->string('department_code')->index();
            $table->string('counseling_category'); // STRESS_BURNOUT, FAMILY_FINANCIAL, SUBSTANCE
            $table->boolean('individual_pii_omitted')->default(true); // 285.6
            $table->boolean('referral_completed')->default(false);
            $table->timestamps();
        });

        Schema::create('sdm_ergonomics_programs', function (Blueprint $table) {
            $table->id();
            $table->string('program_code')->unique();
            $table->string('workplace_site_code')->index();
            $table->integer('pre_intervention_incidents');
            $table->integer('post_intervention_incidents');
            $table->decimal('incident_reduction_pct', 5, 2); // 285.3 & 285.7
            $table->decimal('avoided_cost_usd', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdm_ergonomics_programs');
        Schema::dropIfExists('sdm_eap_counseling_cases');
        Schema::dropIfExists('sdm_occupational_health_surveillances');
    }
};
