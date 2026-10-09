<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_digital_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('signature_id')->unique();
            $table->string('document_code')->index();
            $table->string('signer_identity_id');
            $table->string('signer_role'); // CEO, CFO, LEGAL_DIRECTOR, NOTARY
            $table->string('original_document_sha256'); // 292.1 & 292.4
            $table->string('signature_token');
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();
        });

        Schema::create('gov_verifiable_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('credential_id')->unique();
            $table->string('subject_id')->index();
            $table->string('credential_type'); // MEDICAL_LICENSE, SUPPLIER_QUALIFICATION, PILOT_CERT
            $table->string('issuer_trust_anchor'); // KEMENKES_RI, ISO_REGISTRAR, FAA
            $table->boolean('is_revoked')->default(false); // 292.2 & 292.4
            $table->date('expiry_date');
            $table->timestamps();
        });

        Schema::create('gov_trust_registry_policies', function (Blueprint $table) {
            $table->id();
            $table->string('corridor_code')->unique(); // e.g. ID_SG_CROSS_BORDER
            $table->string('jurisdiction_anchor_origin');
            $table->string('jurisdiction_anchor_destination');
            $table->string('accepted_signature_policy'); // ETSI_PADES, AATL_COMPLIANT, PRIVY_EID
            $table->boolean('is_corridor_active')->default(true); // 292.3 & 292.6
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_trust_registry_policies');
        Schema::dropIfExists('gov_verifiable_credentials');
        Schema::dropIfExists('gov_digital_signatures');
    }
};
