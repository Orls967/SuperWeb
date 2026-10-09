<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // KYC/KYB Documents
        Schema::create('pty_kyc_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('party_id');
            $table->string('document_type'); // akta, nib, npwp, siup, tdp, sertifikat_halal, iso, bpom, gmp, ktp, passport
            $table->string('document_number')->nullable();
            $table->string('document_number_hash')->nullable()->index();
            $table->string('issuer')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, expired
            $table->string('approval_id')->nullable()->comment('FK to core_approvals (string UUID)');
            $table->text('rejection_reason')->nullable();
            $table->string('file_path')->nullable()->comment('core_documents attachment path');
            $table->string('file_checksum')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();

            $table->foreign('party_id')->references('id')->on('pty_parties')->cascadeOnDelete();
        });

        // Sanctions / blacklist screening results
        Schema::create('pty_sanctions_lists', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('simulated'); // simulated, ofac, un, eu, local
            $table->string('entry_type'); // entity, individual, vessel, aircraft
            $table->string('name');
            $table->string('name_normalized');
            $table->string('alias')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('identifier')->nullable()->comment('passport/NPWP/IMO etc');
            $table->string('list_code')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name_normalized');
        });

        // Screening results
        Schema::create('pty_sanctions_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('party_id');
            $table->string('trigger'); // onboarding, pre_transaction, manual
            $table->string('status'); // clear, hit, manual_review
            $table->json('hits')->nullable()->comment('array of sanctions_list IDs matched');
            $table->decimal('match_score', 5, 2)->nullable()->comment('0-100 fuzzy score');
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->foreign('party_id')->references('id')->on('pty_parties')->cascadeOnDelete();
        });

        // Credit profiles
        Schema::create('pty_credit_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('party_id')->unique();
            $table->bigInteger('credit_limit_idr')->default(0)->comment('approved credit limit');
            $table->bigInteger('current_exposure_idr')->default(0)->comment('outstanding across modules');
            $table->integer('internal_score')->default(50)->comment('0-100 simulated score');
            $table->string('risk_tier')->default('medium'); // low, medium, high, blacklisted
            $table->json('exposure_breakdown')->nullable()->comment('per-module breakdown');
            $table->timestamp('last_scored_at')->nullable();
            $table->timestamps();

            $table->foreign('party_id')->references('id')->on('pty_parties')->cascadeOnDelete();
        });

        // Merge log (reversible audit trail of party merges)
        Schema::create('pty_merge_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('source_party_id')->comment('party that was merged away');
            $table->uuid('target_party_id')->comment('surviving party');
            $table->string('merge_rule')->comment('npwp_match|name_phone_match|manual');
            $table->text('reason');
            $table->string('performed_by')->nullable();
            $table->boolean('reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
        });

        // Backlink columns: party_id nullable on existing tables (27.4)
        Schema::table('lgx_carriers', function (Blueprint $table) {
            $table->uuid('party_id')->nullable()->after('id')->index();
        });
        Schema::table('lgx_shipper_accounts', function (Blueprint $table) {
            $table->uuid('party_id')->nullable()->after('id')->index();
        });
        Schema::table('mall_tenants', function (Blueprint $table) {
            $table->uuid('party_id')->nullable()->after('id')->index();
        });
        Schema::table('resto_suppliers', function (Blueprint $table) {
            $table->uuid('party_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('resto_suppliers', function (Blueprint $table) {
            $table->dropColumn('party_id');
        });
        Schema::table('mall_tenants', function (Blueprint $table) {
            $table->dropColumn('party_id');
        });
        Schema::table('lgx_shipper_accounts', function (Blueprint $table) {
            $table->dropColumn('party_id');
        });
        Schema::table('lgx_carriers', function (Blueprint $table) {
            $table->dropColumn('party_id');
        });
        Schema::dropIfExists('pty_merge_logs');
        Schema::dropIfExists('pty_credit_profiles');
        Schema::dropIfExists('pty_sanctions_checks');
        Schema::dropIfExists('pty_sanctions_lists');
        Schema::dropIfExists('pty_kyc_documents');
    }
};
