<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ekstensi kolom pada hsp_patients bila belum ada
        Schema::table('hsp_patients', function (Blueprint $table) {
            if (! Schema::hasColumn('hsp_patients', 'party_id')) {
                $table->unsignedBigInteger('party_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('hsp_patients', 'blind_index_nik')) {
                $table->string('blind_index_nik', 64)->nullable()->index()->after('name');
            }
            if (! Schema::hasColumn('hsp_patients', 'encrypted_surgeries')) {
                $table->json('encrypted_surgeries')->nullable()->after('encrypted_chronic_diagnoses');
            }
            if (! Schema::hasColumn('hsp_patients', 'encrypted_immunizations')) {
                $table->json('encrypted_immunizations')->nullable()->after('encrypted_surgeries');
            }
            if (! Schema::hasColumn('hsp_patients', 'encrypted_medications')) {
                $table->json('encrypted_medications')->nullable()->after('encrypted_immunizations');
            }
        });

        // 2. hsp_patient_passport_events (Human Passport Cryptographic Hash-Chain)
        Schema::create('hsp_patient_passport_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedInteger('sequence_no');
            $table->string('event_type', 48); // ALLERGY, DIAGNOSIS, SURGERY, IMMUNIZATION, MEDICATION, CORRECTION
            $table->json('encrypted_payload');
            $table->string('payload_hash', 64);
            $table->string('prev_hash', 64);
            $table->string('current_hash', 64)->index();
            $table->string('signature', 128);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
            $table->unique(['patient_id', 'sequence_no']);
        });

        // 3. hsp_admissions (Siklus Hidup Rawat Inap 8 Status)
        Schema::create('hsp_admissions', function (Blueprint $table) {
            $table->id();
            $table->string('admission_no', 32)->unique();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('encounter_id')->nullable()->index();
            $table->unsignedBigInteger('bed_id')->nullable()->index();
            $table->string('entitled_class', 16); // VIP, CLASS_1, CLASS_2, CLASS_3, ICU, HDU, ISOLATION
            $table->string('current_class', 16);
            $table->boolean('is_upgraded')->default(false);
            $table->boolean('upgrade_absorbed_by_hospital')->default(false);
            $table->string('status', 32)->default('requested')->index(); // requested, bed_reserved, admitted, transferred, discharge_planned, discharged, readmitted, cancelled
            $table->unsignedBigInteger('deposit_idr')->default(0);
            $table->unsignedBigInteger('total_billed_idr')->default(0);
            $table->timestamp('admitted_at')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
            if (Schema::hasTable('hsp_beds')) {
                $table->foreign('bed_id')->references('id')->on('hsp_beds')->nullOnDelete();
            }
        });

        // 4. hsp_admission_transitions (Riwayat Transisi Append-Only)
        Schema::create('hsp_admission_transitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admission_id')->index();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('trigger', 48);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('transitioned_at');
            $table->timestamps();

            $table->foreign('admission_id')->references('id')->on('hsp_admissions')->cascadeOnDelete();
        });

        // 5. hsp_bed_transitions (Riwayat Transisi Ranjang 7 Status)
        Schema::create('hsp_bed_transitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bed_id')->index();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('reason', 128)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('transitioned_at');
            $table->timestamps();

            $table->foreign('bed_id')->references('id')->on('hsp_beds')->cascadeOnDelete();
        });

        // 6. hsp_clinical_order_transitions (Riwayat Transisi Order 7 Status)
        Schema::create('hsp_clinical_order_transitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->integer('delay_minutes')->default(0);
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('transitioned_at');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('hsp_orders')->cascadeOnDelete();
        });

        // 7. hsp_code_blue_events (Critical Care Code Blue 7 Status)
        Schema::create('hsp_code_blue_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_no', 32)->unique();
            $table->unsignedBigInteger('admission_id')->nullable()->index();
            $table->unsignedBigInteger('encounter_id')->nullable()->index();
            $table->unsignedBigInteger('bed_id')->nullable()->index();
            $table->string('status', 32)->default('detected')->index(); // detected, raised, acknowledged, team_dispatched, resolved, escalated, closed
            $table->string('escalation_level', 24)->default('NURSE'); // NURSE, DOCTOR, TEAM
            $table->timestamp('nurse_ack_at')->nullable();
            $table->timestamp('doctor_ack_at')->nullable();
            $table->timestamp('team_ack_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('proof_hash', 64);
            $table->timestamps();

            if (Schema::hasTable('hsp_beds')) {
                $table->foreign('bed_id')->references('id')->on('hsp_beds')->nullOnDelete();
            }
        });

        // 8. hsp_care_sagas (Orkestrasi Saga Admisi & Kebersihan)
        Schema::create('hsp_care_sagas', function (Blueprint $table) {
            $table->id();
            $table->string('saga_ref', 64)->unique();
            $table->string('saga_type', 48); // INPATIENT_ADMISSION, BED_DISCHARGE_CLEANING
            $table->string('current_step', 48);
            $table->string('status', 24)->default('STARTED'); // STARTED, COMPLETED, FAILED, COMPENSATED
            $table->json('payload');
            $table->text('error_message')->nullable();
            $table->json('compensation_history')->nullable();
            $table->timestamps();
        });

        // 9. hsp_external_factors (Tabel Parameter Kepekaan Berversi T-5 / G7-D59)
        Schema::create('hsp_external_factors', function (Blueprint $table) {
            $table->id();
            $table->string('factor_key', 48)->index();
            $table->string('factor_name', 128);
            $table->string('city_code', 16)->default('JKT')->index();
            $table->bigInteger('factor_value'); // Disimpan dalam skala integer / bps
            $table->date('effective_from');
            $table->string('source_ref', 128);
            $table->string('classification', 24)->default('regulasi'); // regulasi, asumsi_simulasi, fixture
            $table->timestamps();

            $table->unique(['factor_key', 'city_code', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_external_factors');
        Schema::dropIfExists('hsp_care_sagas');
        Schema::dropIfExists('hsp_code_blue_events');
        Schema::dropIfExists('hsp_clinical_order_transitions');
        Schema::dropIfExists('hsp_bed_transitions');
        Schema::dropIfExists('hsp_admission_transitions');
        Schema::dropIfExists('hsp_admissions');
        Schema::dropIfExists('hsp_patient_passport_events');

        Schema::table('hsp_patients', function (Blueprint $table) {
            $table->dropColumn([
                'party_id',
                'blind_index_nik',
                'encrypted_surgeries',
                'encrypted_immunizations',
                'encrypted_medications',
            ]);
        });
    }
};
