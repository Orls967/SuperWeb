<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 50.1 Letter of Credit (UCP 600 Simulasi)
        Schema::create('tf_letters_of_credit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('lc_number', 40)->unique();
            $table->string('type', 24)->default('sight')->comment('sight, usance, standby');
            $table->string('issuing_bank', 100);
            $table->string('advising_bank', 100);
            $table->string('applicant_name', 120);
            $table->string('beneficiary_name', 120);
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('amount_foreign');
            $table->bigInteger('amount_functional_idr');
            $table->date('issue_date');
            $table->date('expiry_date');
            $table->unsignedSmallInteger('tenor_days')->default(0);
            $table->string('status', 24)->default('issued')->comment('issued, advised, amended, presented, discrepancies_found, accepted, paid, expired, cancelled');
            $table->timestamps();
        });

        // 50.2 Dokumen L/C & Pemeriksaan Diskrepansi
        Schema::create('tf_lc_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('letter_of_credit_id');
            $table->string('doc_name', 80);
            $table->string('document_number', 80);
            $table->boolean('has_discrepancy')->default(false);
            $table->string('discrepancy_details', 255)->nullable();
            $table->boolean('is_waived_by_applicant')->default(false);
            $table->timestamps();

            $table->foreign('letter_of_credit_id')->references('id')->on('tf_letters_of_credit')->cascadeOnDelete();
        });

        // 50.3 Documentary Collection (D/P, D/A)
        Schema::create('tf_documentary_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('collection_number', 40)->unique();
            $table->string('type', 10)->comment('DP, DA');
            $table->string('drawee_name', 120);
            $table->string('drawer_name', 120);
            $table->string('collecting_bank', 100);
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('amount_foreign');
            $table->unsignedSmallInteger('tenor_days')->default(0);
            $table->string('status', 24)->default('presented')->comment('presented, accepted, paid, protested');
            $table->timestamps();
        });

        // 50.4 Garansi Bank (Bid Bond, Performance Bond, Advance Payment Guarantee)
        Schema::create('tf_bank_guarantees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('guarantee_number', 40)->unique();
            $table->string('type', 32)->comment('bid_bond, performance_bond, advance_payment');
            $table->string('issuing_bank', 100);
            $table->string('applicant_name', 120);
            $table->string('beneficiary_name', 120);
            $table->bigInteger('amount_idr');
            $table->date('effective_date');
            $table->date('expiry_date');
            $table->bigInteger('claim_amount_idr')->default(0);
            $table->string('status', 24)->default('active')->comment('active, claimed, released, expired');
            $table->timestamps();
        });

        // 50.5 Pembiayaan Perdagangan & SCF (Pre/Post-shipment & Factoring)
        Schema::create('tf_trade_loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('loan_number', 40)->unique();
            $table->string('facility_type', 32)->comment('pre_shipment, post_shipment, factoring, scf');
            $table->string('borrower_name', 120);
            $table->bigInteger('principal_amount_idr');
            $table->decimal('interest_rate_percent', 5, 2)->default(7.50);
            $table->date('disbursed_at');
            $table->date('due_date');
            $table->bigInteger('repaid_amount_idr')->default(0);
            $table->string('status', 24)->default('disbursed')->comment('disbursed, partially_repaid, settled, defaulted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tf_trade_loans');
        Schema::dropIfExists('tf_bank_guarantees');
        Schema::dropIfExists('tf_documentary_collections');
        Schema::dropIfExists('tf_lc_documents');
        Schema::dropIfExists('tf_letters_of_credit');
    }
};
