<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 176.1: Legal matters & privilege classification
        Schema::create('leg_matters', function (Blueprint $table) {
            $table->id();
            $table->string('matter_code')->unique();
            $table->string('case_title');
            $table->string('privilege_classification'); // STRICT_PRIVILEGED, CONFIDENTIAL, PUBLIC
            $table->string('assigned_counsel_role'); // LEGAL_COUNSEL
            $table->timestamps();
        });

        // 176.2: Dispute settlements (settlement posts once, idempotent)
        Schema::create('leg_dispute_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_code')->unique();
            $table->string('matter_code');
            $table->decimal('settlement_amount_idr', 18, 2);
            $table->boolean('is_posted_to_ledger')->default(false);
            $table->string('status')->default('SETTLED');
            $table->timestamps();
        });

        // 176.4: Evidence bundle store with cryptographic checksum
        Schema::create('leg_evidence_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_code')->unique();
            $table->string('matter_code');
            $table->string('document_title');
            $table->string('file_checksum_sha256');
            $table->boolean('is_privileged')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leg_evidence_bundles');
        Schema::dropIfExists('leg_dispute_settlements');
        Schema::dropIfExists('leg_matters');
    }
};
