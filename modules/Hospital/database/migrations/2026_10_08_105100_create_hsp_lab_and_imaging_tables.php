<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 105.1 Lab Catalog
        Schema::create('hsp_lab_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('test_code', 32)->unique();
            $table->string('test_name', 128);
            $table->string('category', 64);
            $table->decimal('reference_min', 8, 2)->nullable();
            $table->decimal('reference_max', 8, 2)->nullable();
            $table->string('unit', 32)->nullable();
            $table->bigInteger('price_idr');
            $table->timestamps();
        });

        // 105.1 & 105.2 Lab Specimens & Hash-chain custody
        Schema::create('hsp_lab_specimens', function (Blueprint $table) {
            $table->id();
            $table->string('specimen_barcode', 64)->unique();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('encounter_id')->nullable();
            $table->string('sample_type', 32); // BLOOD, URINE, TISSUE, SWAB
            $table->dateTime('collected_at');
            $table->decimal('transport_temp_c', 4, 1)->nullable();
            $table->string('custody_hash', 64); // Tamper-evident hash-chain
            $table->string('status', 32)->default('COLLECTED'); // COLLECTED, IN_TRANSIT, RECEIVED_LAB, REJECTED, PROCESSED
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
        });

        // 105.1 & 105.3 Lab Results with tiered auto-verify & critical value alerts
        Schema::create('hsp_lab_results', function (Blueprint $table) {
            $table->id();
            $table->string('result_code', 32)->unique();
            $table->unsignedBigInteger('specimen_id');
            $table->string('test_code', 32);
            $table->decimal('numeric_value', 8, 2)->nullable();
            $table->string('text_value', 128)->nullable();
            $table->boolean('is_critical')->default(false);
            $table->string('verified_by_pathologist_hash', 64)->nullable();
            $table->string('status', 32)->default('PRELIMINARY'); // PRELIMINARY, AUTO_VERIFIED, PATHOLOGIST_VERIFIED, CRITICAL_HOLD
            $table->timestamps();

            $table->foreign('specimen_id')->references('id')->on('hsp_lab_specimens')->cascadeOnDelete();
        });

        // 105.1 Imaging Studies (DICOM metadata)
        Schema::create('hsp_imaging_studies', function (Blueprint $table) {
            $table->id();
            $table->string('study_instance_uid', 64)->unique();
            $table->unsignedBigInteger('patient_id');
            $table->string('modality', 16); // CT, MRI, USG, XR
            $table->string('body_part', 64);
            $table->dateTime('performed_at');
            $table->integer('turnaround_minutes')->default(0);
            $table->text('radiologist_findings')->nullable();
            $table->bigInteger('fee_idr');
            $table->string('status', 32)->default('COMPLETED');
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_imaging_studies');
        Schema::dropIfExists('hsp_lab_results');
        Schema::dropIfExists('hsp_lab_specimens');
        Schema::dropIfExists('hsp_lab_catalog');
    }
};
