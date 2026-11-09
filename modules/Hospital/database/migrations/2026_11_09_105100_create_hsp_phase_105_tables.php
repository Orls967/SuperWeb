<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Master Katalog LOINC 10.000 Parameter (Fase 105)
        if (! Schema::hasTable('hsp_lab_catalogs')) {
            Schema::create('hsp_lab_catalogs', function (Blueprint $table): void {
                $table->id();
                $table->string('loinc_num', 32)->unique();
                $table->string('component', 128);
                $table->string('property', 32)->default('SCnc');
                $table->string('time_aspect', 16)->default('Pt');
                $table->string('system_specimen', 64)->default('Ser/Plas');
                $table->string('scale_type', 16)->default('Qn');
                $table->string('method_type', 64)->nullable();
                $table->string('class_name', 64)->default('CHEM');
                $table->decimal('reference_min', 12, 4)->nullable();
                $table->decimal('reference_max', 12, 4)->nullable();
                $table->string('unit', 32)->nullable();
                $table->decimal('panic_low', 12, 4)->nullable();
                $table->decimal('panic_high', 12, 4)->nullable();
                $table->bigInteger('price_idr')->default(0);
                $table->boolean('is_orderable')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['class_name', 'is_orderable'], 'hsp_loinc_class_ord_idx');
                $table->index('component', 'hsp_loinc_comp_idx');
            });
        }

        // 2. Tabel Rantai Kustodi Spesimen Kriptografis 3-Hop (Fase 105)
        if (! Schema::hasTable('hsp_specimen_custody_chain_logs')) {
            Schema::create('hsp_specimen_custody_chain_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('specimen_id')->constrained('hsp_lab_specimens')->cascadeOnDelete();
                $table->integer('hop_number');
                $table->string('from_handler', 64);
                $table->string('to_handler', 64);
                $table->decimal('transport_temp_celsius', 5, 2)->nullable();
                $table->string('gps_coordinates', 64)->nullable();
                $table->string('storage_container_barcode', 64)->nullable();
                $table->string('prev_hash', 64);
                $table->string('custody_hash', 64);
                $table->string('notes', 255)->nullable();
                $table->timestamp('transferred_at');
                $table->timestamps();

                $table->index(['specimen_id', 'hop_number'], 'hsp_spec_hop_num_idx');
                $table->index('custody_hash', 'hsp_spec_cust_hash_idx');
            });
        }

        // 3. Tabel Notifikasi Nilai Kritis & Hold Patolog (Fase 105)
        if (! Schema::hasTable('hsp_critical_notifications')) {
            Schema::create('hsp_critical_notifications', function (Blueprint $table): void {
                $table->id();
                $table->string('notification_uid', 64)->unique();
                $table->foreignId('specimen_id')->constrained('hsp_lab_specimens')->cascadeOnDelete();
                $table->foreignId('patient_id')->constrained('hsp_patients')->cascadeOnDelete();
                $table->string('test_code', 32);
                $table->string('analyte_name', 128);
                $table->decimal('measured_value', 12, 4);
                $table->decimal('panic_threshold', 12, 4);
                $table->string('panic_type', 16); // LOW, HIGH
                $table->string('notification_protocol', 64);
                $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('doctor_phone')->nullable(); // Encrypted
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->boolean('read_back_confirmed')->default(false);
                $table->string('read_back_recipient_name', 128)->nullable();
                $table->timestamp('pathologist_verified_at')->nullable();
                $table->string('pathologist_signature_hash', 64)->nullable();
                $table->string('status', 32)->default('CRITICAL_HOLD');
                $table->timestamps();

                $table->index(['status', 'created_at'], 'hsp_crit_stat_date_idx');
                $table->index(['specimen_id', 'test_code'], 'hsp_crit_spec_test_idx');
            });
        }

        // 4. Tabel SOP Instances DICOM Part 10 (Fase 105)
        if (! Schema::hasTable('hsp_dicom_instances')) {
            Schema::create('hsp_dicom_instances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('series_id')->constrained('hsp_imaging_series')->cascadeOnDelete();
                $table->string('sop_instance_uid', 64)->unique();
                $table->integer('instance_number')->default(1);
                $table->string('sop_class_uid', 64)->default('1.2.840.10008.5.1.4.1.1.2');
                $table->integer('rows')->default(512);
                $table->integer('columns')->default(512);
                $table->integer('bits_allocated')->default(16);
                $table->decimal('window_center', 8, 2)->default(40.00);
                $table->decimal('window_width', 8, 2)->default(80.00);
                $table->string('pixel_spacing', 32)->default('0.5\\0.5');
                $table->string('storage_path', 255);
                $table->string('checksum_sha256', 64);
                $table->timestamps();

                $table->index(['series_id', 'instance_number'], 'hsp_dicom_ser_inst_idx');
            });
        }

        // 5. Tabel Kendali Mutu Laboratorium Westgard QC (Fase 105)
        if (! Schema::hasTable('hsp_lab_quality_controls')) {
            Schema::create('hsp_lab_quality_controls', function (Blueprint $table): void {
                $table->id();
                $table->string('analyzer_code', 32);
                $table->string('lot_number', 64);
                $table->string('control_level', 16); // LEVEL_1, LEVEL_2, LEVEL_3
                $table->string('test_code', 32);
                $table->decimal('target_mean', 12, 4);
                $table->decimal('target_sd', 12, 4);
                $table->decimal('measured_value', 12, 4);
                $table->decimal('z_score', 8, 4);
                $table->string('westgard_violation', 32)->nullable(); // 1-2S, 1-3S, 2-2S, R-4S, 4-1S, 10-X
                $table->boolean('is_rejected')->default(false);
                $table->boolean('is_resolved')->default(true);
                $table->foreignId('technologist_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('evaluated_at');
                $table->timestamps();

                $table->index(['analyzer_code', 'test_code', 'is_rejected'], 'hsp_qc_anlz_test_idx');
            });
        }

        // 6. Tabel Manifest Pengiriman Rujukan Lab Eksternal (Fase 105)
        if (! Schema::hasTable('hsp_lab_outsourcing_manifests')) {
            Schema::create('hsp_lab_outsourcing_manifests', function (Blueprint $table): void {
                $table->id();
                $table->string('manifest_number', 64)->unique();
                $table->foreignId('contract_id')->constrained('hsp_lab_partner_contracts')->cascadeOnDelete();
                $table->integer('specimen_count')->default(0);
                $table->string('courier_batch_barcode', 64);
                $table->decimal('departure_temperature_celsius', 5, 2);
                $table->decimal('arrival_temperature_celsius', 5, 2)->nullable();
                $table->string('handover_hash', 64);
                $table->string('status', 32)->default('DISPATCHED');
                $table->timestamps();

                $table->index(['contract_id', 'status'], 'hsp_manif_cont_stat_idx');
            });
        }

        // 7. Tabel Log Dosimetri Radiasi Pasien (Fase 105)
        if (! Schema::hasTable('hsp_radiation_dosimetry_logs')) {
            Schema::create('hsp_radiation_dosimetry_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('study_id')->constrained('hsp_imaging_studies')->cascadeOnDelete();
                $table->foreignId('patient_id')->constrained('hsp_patients')->cascadeOnDelete();
                $table->decimal('ctdi_vol_mgy', 8, 2);
                $table->decimal('dlp_mgy_cm', 10, 2);
                $table->decimal('effective_dose_msv', 8, 3)->nullable();
                $table->decimal('ssde_mgy', 8, 2)->nullable();
                $table->boolean('drl_exceeded')->default(false);
                $table->string('safety_officer_review', 255)->nullable();
                $table->timestamps();

                $table->index(['patient_id', 'drl_exceeded'], 'hsp_rad_pat_drl_idx');
            });
        }

        // 8. Tabel Log Verifikasi Patolog & Digital Signature (Fase 105)
        if (! Schema::hasTable('hsp_pathologist_verification_logs')) {
            Schema::create('hsp_pathologist_verification_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('specimen_id')->constrained('hsp_lab_specimens')->cascadeOnDelete();
                $table->string('test_code', 32);
                $table->foreignId('pathologist_id')->constrained('users')->cascadeOnDelete();
                $table->string('digital_signature_hash', 64);
                $table->text('clinical_interpretation')->nullable();
                $table->timestamp('signed_at');
                $table->timestamps();

                $table->index(['specimen_id', 'test_code'], 'hsp_path_spec_test_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_pathologist_verification_logs');
        Schema::dropIfExists('hsp_radiation_dosimetry_logs');
        Schema::dropIfExists('hsp_lab_outsourcing_manifests');
        Schema::dropIfExists('hsp_lab_quality_controls');
        Schema::dropIfExists('hsp_dicom_instances');
        Schema::dropIfExists('hsp_critical_notifications');
        Schema::dropIfExists('hsp_specimen_custody_chain_logs');
        Schema::dropIfExists('hsp_lab_catalogs');
    }
};
