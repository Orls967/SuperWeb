<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 88.1 Billing Episode & Folio
        Schema::create('hsp_billing_episodes', function (Blueprint $table) {
            $table->id();
            $table->string('episode_code', 32)->unique();
            $table->unsignedBigInteger('encounter_id');
            $table->bigInteger('escrow_deposit_idr')->default(0);
            $table->bigInteger('total_charges_idr')->default(0);
            $table->bigInteger('bpjs_coverage_idr')->default(0);
            $table->bigInteger('insurance_copay_idr')->default(0);
            $table->bigInteger('patient_share_idr')->default(0);
            $table->string('status', 32)->default('OPEN'); // OPEN, BILLED, SETTLED, REFUNDED
            $table->timestamps();

            $table->foreign('encounter_id')->references('id')->on('hsp_encounters')->cascadeOnDelete();
        });

        // 88.1 Folio Line Items
        Schema::create('hsp_folio_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_episode_id');
            $table->string('item_category', 32); // BED_DAY, PROCEDURE, PHARMACY, LAB, RADIOLOGY
            $table->string('description', 128);
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_price_idr');
            $table->bigInteger('subtotal_idr');
            $table->timestamps();

            $table->foreign('billing_episode_id')->references('id')->on('hsp_billing_episodes')->cascadeOnDelete();
        });

        // 88.3 e-Prescriptions & pharmacy dispense
        Schema::create('hsp_prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('rx_code', 32)->unique();
            $table->unsignedBigInteger('encounter_id');
            $table->string('drug_code', 32);
            $table->string('drug_name', 128);
            $table->integer('qty_prescribed');
            $table->string('dosage_instructions', 128);
            $table->boolean('contraindication_alert')->default(false);
            $table->string('status', 32)->default('PRESCRIBED'); // PRESCRIBED, DISPENSED
            $table->timestamps();

            $table->foreign('encounter_id')->references('id')->on('hsp_encounters')->cascadeOnDelete();
        });

        // 88.5 Medical cold-chain fridge breach alerts & supplier hold
        Schema::create('hsp_med_fridge_breaches', function (Blueprint $table) {
            $table->id();
            $table->string('breach_code', 32)->unique();
            $table->string('fridge_unit_code', 32);
            $table->string('lot_number', 64);
            $table->decimal('recorded_temp_c', 4, 1);
            $table->unsignedBigInteger('supplier_id');
            $table->bigInteger('held_supplier_payable_idr');
            $table->string('status', 32)->default('QUARANTINED'); // QUARANTINED, RECALLED, RESOLVED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_med_fridge_breaches');
        Schema::dropIfExists('hsp_prescriptions');
        Schema::dropIfExists('hsp_folio_items');
        Schema::dropIfExists('hsp_billing_episodes');
    }
};
