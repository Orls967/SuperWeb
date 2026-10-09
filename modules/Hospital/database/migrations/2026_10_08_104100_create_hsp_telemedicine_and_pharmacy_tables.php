<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 104.1 Telemedicine Consultations
        Schema::create('hsp_tele_consults', function (Blueprint $table) {
            $table->id();
            $table->string('consult_code', 32)->unique();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->string('channel_type', 32)->default('VIDEO'); // VIDEO, CHAT
            $table->string('triage_category', 32)->default('GREEN'); // GREEN, YELLOW, RED
            $table->text('chief_complaint');
            $table->text('clinical_notes')->nullable();
            $table->string('status', 32)->default('SCHEDULED'); // SCHEDULED, IN_PROGRESS, COMPLETED, CANCELLED
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
        });

        // 104.1 Pharmacy Branches (Network & Partner)
        Schema::create('hsp_pharmacy_branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code', 32)->unique();
            $table->string('name', 128);
            $table->string('type', 32)->default('INTERNAL'); // INTERNAL, PARTNER_PARTY
            $table->string('city', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 104.1 & 104.2 e-Pharmacy Orders with Doctor Digital Signature Hash
        Schema::create('hsp_epharmacy_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 32)->unique();
            $table->unsignedBigInteger('tele_consult_id')->nullable();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('pharmacy_branch_id');
            $table->string('drug_code', 32);
            $table->string('drug_name', 128);
            $table->integer('quantity');
            $table->bigInteger('total_price_idr');
            $table->string('drug_classification', 32)->default('REGULAR'); // REGULAR, NARCOTIC_PSYCHOTROPIC
            $table->string('doctor_signature_hash', 64);
            $table->string('second_doctor_approval_hash', 64)->nullable();
            $table->boolean('is_chronic_subscription')->default(false);
            $table->string('pod_signature_hash', 64)->nullable();
            $table->string('delivery_status', 32)->default('PROCESSING'); // PROCESSING, DISPATCHED, DELIVERED
            $table->string('status', 32)->default('CONFIRMED'); // CONFIRMED, DISPENSED, DELIVERED, CANCELLED
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
            $table->foreign('pharmacy_branch_id')->references('id')->on('hsp_pharmacy_branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_epharmacy_orders');
        Schema::dropIfExists('hsp_pharmacy_branches');
        Schema::dropIfExists('hsp_tele_consults');
    }
};
