<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 87.1 Patients & Cryptographic Health Passport
        Schema::create('hsp_patients', function (Blueprint $table) {
            $table->id();
            $table->string('mrn', 32)->unique(); // Medical Record Number
            $table->string('name', 128);
            $table->date('date_of_birth');
            $table->string('blood_type', 8);
            $table->json('encrypted_allergies')->nullable();
            $table->json('encrypted_chronic_diagnoses')->nullable();
            $table->string('passport_hash', 64); // Tamper-evident hash chain
            $table->timestamps();
        });

        // 87.1 Encounters
        Schema::create('hsp_encounters', function (Blueprint $table) {
            $table->id();
            $table->string('encounter_code', 32)->unique();
            $table->unsignedBigInteger('patient_id');
            $table->string('encounter_type', 32); // OUTPATIENT, INPATIENT, EMERGENCY
            $table->dateTime('admitted_at');
            $table->dateTime('discharged_at')->nullable();
            $table->string('status', 32)->default('ADMITTED'); // ADMITTED, DISCHARGED
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
        });

        // 87.1 & 87.3 Hospital beds & real-time allocation
        Schema::create('hsp_beds', function (Blueprint $table) {
            $table->id();
            $table->string('bed_code', 32)->unique();
            $table->string('ward_name', 64);
            $table->string('room_number', 16);
            $table->string('bed_class', 16); // VIP, CLASS_1, CLASS_2, CLASS_3, ICU, HDU, ISOLATION
            $table->bigInteger('rate_per_day_idr');
            $table->string('status', 32)->default('AVAILABLE'); // AVAILABLE, OCCUPIED, CLEANING, MAINTENANCE
            $table->unsignedBigInteger('current_encounter_id')->nullable();
            $table->timestamps();
        });

        // 87.1 & 87.4 Clinical Pathway Doctor Orders
        Schema::create('hsp_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 32)->unique();
            $table->unsignedBigInteger('encounter_id');
            $table->string('order_type', 32); // MEDICATION, LAB, RADIOLOGY, PROCEDURE
            $table->string('description', 255);
            $table->integer('sequence_step')->default(1);
            $table->dateTime('scheduled_at');
            $table->string('status', 32)->default('PENDING'); // PENDING, IN_PROGRESS, RESULTED, DELAYED
            $table->boolean('delay_alert_sent')->default(false);
            $table->timestamps();

            $table->foreign('encounter_id')->references('id')->on('hsp_encounters')->cascadeOnDelete();
        });

        // 87.5 IoT critical care vitals telemetry and code blue alerts
        Schema::create('hsp_vitals_telemetries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('encounter_id');
            $table->decimal('spo2_percent', 5, 2);
            $table->integer('heart_rate_bpm');
            $table->decimal('temp_c', 4, 1);
            $table->boolean('code_blue_triggered')->default(false);
            $table->string('proof_hash', 64);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('encounter_id')->references('id')->on('hsp_encounters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_vitals_telemetries');
        Schema::dropIfExists('hsp_orders');
        Schema::dropIfExists('hsp_beds');
        Schema::dropIfExists('hsp_encounters');
        Schema::dropIfExists('hsp_patients');
    }
};
