<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 106.1 Clinical Trials
        Schema::create('hsp_trials', function (Blueprint $table) {
            $table->id();
            $table->string('trial_code', 32)->unique();
            $table->string('title', 160);
            $table->string('phase', 16); // PHASE_I, PHASE_II, PHASE_III, PHASE_IV
            $table->string('sponsor_name', 128);
            $table->integer('target_subjects')->default(50);
            $table->string('status', 32)->default('RECRUITING'); // RECRUITING, ACTIVE, COMPLETED, LOCKED
            $table->timestamps();
        });

        // 106.1 Trial Sites
        Schema::create('hsp_trial_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trial_id');
            $table->string('site_code', 32)->unique();
            $table->string('hospital_name', 128);
            $table->string('principal_investigator', 128);
            $table->timestamps();

            $table->foreign('trial_id')->references('id')->on('hsp_trials')->cascadeOnDelete();
        });

        // 106.1 & 106.2 Trial Subjects with Informed Consent Hash & Deterministic Seed
        Schema::create('hsp_trial_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('subject_code', 32)->unique();
            $table->unsignedBigInteger('trial_id');
            $table->unsignedBigInteger('patient_id');
            $table->string('anonymized_hash', 64);
            $table->string('informed_consent_hash', 64);
            $table->string('randomized_arm', 32); // ARM_A_ACTIVE, ARM_B_PLACEBO
            $table->string('status', 32)->default('ENROLLED'); // ENROLLED, COMPLETED, WITHDRAWN
            $table->timestamps();

            $table->foreign('trial_id')->references('id')->on('hsp_trials')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
            $table->unique(['trial_id', 'patient_id']); // 106.6 (a) subjek ganda dalam 1 studi ditolak
        });

        // 106.3 Data Vault & Access Requests with Four-Eyes Approval
        Schema::create('hsp_data_vault_accesses', function (Blueprint $table) {
            $table->id();
            $table->string('request_code', 32)->unique();
            $table->unsignedBigInteger('trial_id');
            $table->string('researcher_id', 64);
            $table->text('purpose');
            $table->boolean('approved_by_irb')->default(false);
            $table->string('irb_approval_hash', 64)->nullable();
            $table->string('status', 32)->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->timestamps();

            $table->foreign('trial_id')->references('id')->on('hsp_trials')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_data_vault_accesses');
        Schema::dropIfExists('hsp_trial_subjects');
        Schema::dropIfExists('hsp_trial_sites');
        Schema::dropIfExists('hsp_trials');
    }
};
