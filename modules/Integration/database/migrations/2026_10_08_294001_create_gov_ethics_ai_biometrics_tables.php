<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_ethics_impact_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('assessment_code')->unique();
            $table->string('system_name');
            $table->string('data_sensitivity_tier'); // MEDICAL, LOCATION, BIOMETRICS, CHILD_DATA
            $table->text('necessity_proportionality_justification'); // 294.1 & 294.7 Mandatory
            $table->boolean('is_approved_by_ethics_board')->default(false); // 294.1 & 294.5
            $table->date('annual_review_date');
            $table->timestamps();
        });

        Schema::create('gov_biometric_identities', function (Blueprint $table) {
            $table->id();
            $table->string('subject_identity_id')->unique();
            $table->string('biometric_type'); // FACIAL_SCAN, FINGERPRINT
            $table->string('encrypted_template_hash'); // 294.2 Template protection
            $table->boolean('has_alternative_pin_path')->default(true); // 294.2 & 294.8 Alternative non-biometric path
            $table->boolean('is_biometric_revoked')->default(false); // 294.2 Deletion/revocation
            $table->timestamps();
        });

        Schema::create('gov_child_safeguards', function (Blueprint $table) {
            $table->id();
            $table->string('student_user_id')->unique();
            $table->integer('age_years');
            $table->string('parent_guardian_consent_ref')->nullable(); // 294.4
            $table->boolean('targeted_adult_contact_blocked')->default(true); // 294.4 & 294.5
            $table->boolean('guardian_controls_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_child_safeguards');
        Schema::dropIfExists('gov_biometric_identities');
        Schema::dropIfExists('gov_ethics_impact_assessments');
    }
};
