<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_internal_audit_engagements', function (Blueprint $table) {
            $table->id();
            $table->string('engagement_code')->unique();
            $table->string('auditable_entity');
            $table->string('scope_description');
            $table->integer('sample_seed')->default(42); // 293.5 & 293.8 Reproducible seed
            $table->string('status')->default('FIELDWORK'); // FIELDWORK, FINDINGS_ISSUED, COMPLETED
            $table->timestamps();
        });

        Schema::create('gov_internal_audit_findings', function (Blueprint $table) {
            $table->id();
            $table->string('finding_code')->unique();
            $table->string('engagement_code')->index();
            $table->string('finding_title');
            $table->string('severity'); // HIGH, MEDIUM, LOW, OUT_OF_SCOPE_OBSERVATION (293.6)
            $table->text('management_response')->nullable(); // 293.5 & 293.7 Mandatory
            $table->string('closure_evidence_ref')->nullable(); // 293.5 Mandatory
            $table->string('status')->default('OPEN'); // OPEN, MANAGEMENT_RESPONDED, CLOSED_VERIFIED
            $table->timestamps();
        });

        Schema::create('gov_external_auditor_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditor_identity_id');
            $table->string('scoped_package_ref');
            $table->boolean('is_read_only')->default(true); // 293.4 & 293.5
            $table->dateTime('accessed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_external_auditor_access_logs');
        Schema::dropIfExists('gov_internal_audit_findings');
        Schema::dropIfExists('gov_internal_audit_engagements');
    }
};
