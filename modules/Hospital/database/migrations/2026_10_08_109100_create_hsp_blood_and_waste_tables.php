<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 109.1 Blood Bank Bags with Expiry and Cryptographic Screening Hash
        Schema::create('hsp_blood_bags', function (Blueprint $table) {
            $table->id();
            $table->string('bag_serial_number', 64)->unique();
            $table->string('blood_type', 8); // A+, A-, B+, B-, AB+, AB-, O+, O-
            $table->string('component_type', 32); // WHOLE_BLOOD, PACKED_RED_CELLS, PLATELETS, FRESH_FROZEN_PLASMA
            $table->integer('volume_ml')->default(350);
            $table->date('expiry_date');
            $table->string('donor_screening_hash', 64);
            $table->unsignedBigInteger('reserved_for_encounter_id')->nullable();
            $table->unsignedBigInteger('issued_to_encounter_id')->nullable();
            $table->string('status', 32)->default('AVAILABLE'); // AVAILABLE, RESERVED, ISSUED, QUARANTINED, RECALLED
            $table->timestamps();
        });

        // 109.3 Medical Equipment Calibrations
        Schema::create('hsp_medical_equipments', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_code', 32)->unique();
            $table->string('name', 128);
            $table->string('category', 64); // VENTILATOR, DEFIBRILLATOR, ANESTHESIA_MACHINE, XRAY
            $table->date('calibration_expires_at');
            $table->string('calibration_certificate_hash', 64);
            $table->string('status', 32)->default('CERTIFIED'); // CERTIFIED, CALIBRATION_EXPIRED, UNDER_MAINTENANCE
            $table->timestamps();
        });

        // 109.5 Hazardous Medical Waste Manifests (B3)
        Schema::create('hsp_medical_waste_manifests', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_number', 32)->unique();
            $table->string('waste_category', 32); // SHARPS, INFECTIOUS, PHARMACEUTICAL, CHEMICAL
            $table->decimal('weight_kg', 8, 2);
            $table->string('certified_vendor_party_id', 64);
            $table->string('custody_hash', 64);
            $table->string('destruction_certificate_hash', 64)->nullable();
            $table->string('status', 32)->default('DISPATCHED'); // DISPATCHED, INCINERATED, COMPLETED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_medical_waste_manifests');
        Schema::dropIfExists('hsp_medical_equipments');
        Schema::dropIfExists('hsp_blood_bags');
    }
};
