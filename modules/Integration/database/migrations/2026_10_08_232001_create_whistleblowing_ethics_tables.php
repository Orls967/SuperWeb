<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_whistleblower_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code')->unique();
            $table->string('anonymous_token')->unique();
            $table->string('category'); // CODE_OF_CONDUCT, HARASSMENT, CORRUPTION, SAFETY, FRAUD
            $table->text('encrypted_summary');
            $table->string('primary_investigator_id')->nullable();
            $table->string('secondary_investigator_id')->nullable(); // Four-eyes principle
            $table->string('status')->default('SUBMITTED'); // SUBMITTED, TRIAGED, INVESTIGATING, SUBSTANTIATED, CONCLUDED
            $table->boolean('is_anonymous')->default(true);
            $table->boolean('identity_leaked')->default(false); // 232.6 edge case
            $table->boolean('has_fraud_indication')->default(false);
            $table->string('fraud_mesh_case_id')->nullable(); // 232.4 bridge
            $table->timestamps();
        });

        Schema::create('gov_anti_retaliation_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_code')->unique();
            $table->string('report_code')->index();
            $table->string('reporter_token_or_id');
            $table->string('treatment_anomaly_type'); // DEMOTION, UNJUST_PERFORMANCE_DROP, SHIFT_CANCELLATION, SLAPP_LAWSUIT
            $table->string('investigation_status')->default('TRIGGERED'); // TRIGGERED, INVESTIGATING, SANCTIONED
            $table->boolean('legal_support_provided')->default(false); // 232.7 Anti-SLAPP support
            $table->timestamps();
        });

        Schema::create('gov_ethics_sanctions', function (Blueprint $table) {
            $table->id();
            $table->string('sanction_code')->unique();
            $table->string('report_code')->index();
            $table->string('subject_person_id')->index();
            $table->string('violation_severity'); // MINOR, MODERATE, SEVERE, GROSS_MISCONDUCT
            $table->string('sanction_applied'); // WRITTEN_WARNING, SUSPENSION, TERMINATION, LEGAL_PROSECUTION
            $table->string('appeal_status')->default('NONE'); // NONE, APPEALED, UPHELD, OVERTURNED
            $table->boolean('isolated_from_hr_view')->default(true); // 232.3 strict access isolation
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_ethics_sanctions');
        Schema::dropIfExists('gov_anti_retaliation_alerts');
        Schema::dropIfExists('gov_whistleblower_reports');
    }
};
