<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. hsp_ina_cbg_tariffs: Master Data Tarif INA-CBGs & Kelas Rawat
        if (! Schema::hasTable('hsp_ina_cbg_tariffs')) {
            Schema::create('hsp_ina_cbg_tariffs', function (Blueprint $table) {
                $table->id();
                $table->string('cbg_code', 16)->unique(); // Misal: I-4-10-I, Q-5-18-I
                $table->string('description', 255);
                $table->string('care_type', 16)->default('INPATIENT'); // INPATIENT, OUTPATIENT, EMERGENCY
                $table->string('severity_level', 8)->default('I'); // I, II, III
                $table->string('hospital_class', 8)->default('B'); // A, B, C, D (Kelas RS)
                $table->bigInteger('base_tariff_idr');
                $table->bigInteger('class_3_tariff_idr');
                $table->bigInteger('class_2_tariff_idr');
                $table->bigInteger('class_1_tariff_idr');
                $table->bigInteger('vip_max_topup_idr'); // Plafon maksimal top up selisih kelas (Permenkes 3/2023)
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. hsp_guarantee_letters: Surat Jaminan Pelayanan (SJP / GL) Asuransi Swasta & BPJS
        if (! Schema::hasTable('hsp_guarantee_letters')) {
            Schema::create('hsp_guarantee_letters', function (Blueprint $table) {
                $table->id();
                $table->string('guarantee_number', 64)->unique();
                $table->unsignedBigInteger('patient_id')->index();
                $table->unsignedBigInteger('encounter_id')->nullable()->index();
                $table->string('payer_name', 128); // BPJS_KESEHATAN, ALLIANZ, PRUDENTIAL, MANDIRI_INHEALTH
                $table->string('policy_number', 64);
                $table->string('guarantee_type', 32)->default('PRE_AUTHORIZATION'); // PRE_AUTHORIZATION, FINAL_GUARANTEE, DISCHARGE_APPROVAL
                $table->bigInteger('approved_coverage_limit_idr')->default(0);
                $table->bigInteger('utilized_amount_idr')->default(0);
                $table->bigInteger('deductible_idr')->default(0);
                $table->string('status', 32)->default('ISSUED'); // ISSUED, UTILIZED, REVISED, REVOKED, EXPIRED
                $table->dateTime('valid_until');
                $table->string('auth_code', 64)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('patient_id')->references('id')->on('hsp_patients')->cascadeOnDelete();
                $table->foreign('encounter_id')->references('id')->on('hsp_encounters')->nullOnDelete();
            });
        }

        // 3. hsp_claim_waterfalls: Koordinasi Manfaat (CoB) Waterfall Multi-Layer
        if (! Schema::hasTable('hsp_claim_waterfalls')) {
            Schema::create('hsp_claim_waterfalls', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('billing_episode_id')->index();
                $table->integer('step_order'); // 1 = Primary Payer, 2 = Secondary Payer, 3 = Patient Share
                $table->string('payer_type', 32); // BPJS, PRIVATE_INSURANCE, CORPORATE, PATIENT_OOP
                $table->string('payer_reference', 64)->nullable(); // No SJP / Polis
                $table->bigInteger('gross_billed_idr');
                $table->bigInteger('deductions_idr')->default(0);
                $table->bigInteger('covered_amount_idr')->default(0);
                $table->bigInteger('remaining_balance_idr')->default(0);
                $table->string('status', 32)->default('PENDING'); // PENDING, ADJUDICATED, SETTLED, DISPUTED
                $table->dateTime('settled_at')->nullable();
                $table->timestamps();

                $table->foreign('billing_episode_id')->references('id')->on('hsp_billing_episodes')->cascadeOnDelete();
            });
        }

        // 4. hsp_cold_chain_telemetries: Log Telemetri Suhu & Kelembaban Cold Chain Farmasi
        if (! Schema::hasTable('hsp_cold_chain_telemetries')) {
            Schema::create('hsp_cold_chain_telemetries', function (Blueprint $table) {
                $table->id();
                $table->string('sensor_device_code', 32)->index();
                $table->string('fridge_unit_code', 32)->index();
                $table->string('storage_zone', 32); // CHILLER_2_TO_8, FREEZER_MINUS_20, ULTRA_LOW_MINUS_80
                $table->decimal('temperature_c', 5, 2);
                $table->decimal('humidity_pct', 5, 2)->nullable();
                $table->boolean('is_excursion')->default(false);
                $table->string('excursion_severity', 16)->nullable(); // NORMAL, WARNING, CRITICAL
                $table->dateTime('recorded_at')->index();
                $table->timestamps();
            });
        }

        // 5. hsp_waste_custody_logs: Rantai Kustodi Limbah B3 Elektronik (Festronik Hash Chain)
        if (! Schema::hasTable('hsp_waste_custody_logs')) {
            Schema::create('hsp_waste_custody_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('manifest_id')->index();
                $table->string('event_type', 32); // GENERATED_AT_SOURCE, STORED_AT_TPS, HANDED_TO_TRANSPORTER, WEIGHED_AT_FACILITY, INCINERATED
                $table->string('handler_party_name', 128);
                $table->string('transporter_plate_no', 32)->nullable();
                $table->decimal('weight_kg', 8, 3);
                $table->string('storage_bin_code', 32)->nullable();
                $table->string('prev_custody_hash', 64);
                $table->string('current_custody_hash', 64); // SHA-256 tamper-proof chain
                $table->string('digital_signature', 128)->nullable();
                $table->dateTime('event_at');
                $table->timestamps();

                $table->foreign('manifest_id')->references('id')->on('hsp_medical_waste_manifests')->cascadeOnDelete();
            });
        }

        // 6. hsp_claim_disputes: Dispute & Banding Klaim Asuransi/BPJS
        if (! Schema::hasTable('hsp_claim_disputes')) {
            Schema::create('hsp_claim_disputes', function (Blueprint $table) {
                $table->id();
                $table->string('dispute_number', 64)->unique();
                $table->unsignedBigInteger('billing_episode_id')->index();
                $table->string('claim_reference', 64)->index();
                $table->string('dispute_reason_code', 32); // CODING_DISPUTE, MISSING_RESUME, MEDICAL_NECESSITY, ELIGIBILITY
                $table->bigInteger('disputed_amount_idr');
                $table->bigInteger('reconciled_amount_idr')->default(0);
                $table->string('status', 32)->default('SUBMITTED'); // SUBMITTED, UNDER_REVIEW, UPHELD, OVERTURNED, CLOSED
                $table->text('hospital_justification');
                $table->text('payer_verdict')->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->timestamps();

                $table->foreign('billing_episode_id')->references('id')->on('hsp_billing_episodes')->cascadeOnDelete();
            });
        }

        // 7. Tambahkan source_ward_code pada hsp_medical_waste_manifests
        if (Schema::hasTable('hsp_medical_waste_manifests') && ! Schema::hasColumn('hsp_medical_waste_manifests', 'source_ward_code')) {
            Schema::table('hsp_medical_waste_manifests', function (Blueprint $table) {
                $table->string('source_ward_code', 32)->nullable()->after('weight_kg');
            });
        }

        // 8. Tambahkan fridge_unit_code pada hsp_branch_inventory_lots
        if (Schema::hasTable('hsp_branch_inventory_lots') && ! Schema::hasColumn('hsp_branch_inventory_lots', 'fridge_unit_code')) {
            Schema::table('hsp_branch_inventory_lots', function (Blueprint $table) {
                $table->string('fridge_unit_code', 32)->nullable()->after('lot_number');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_claim_disputes');
        Schema::dropIfExists('hsp_waste_custody_logs');
        Schema::dropIfExists('hsp_cold_chain_telemetries');
        Schema::dropIfExists('hsp_claim_waterfalls');
        Schema::dropIfExists('hsp_guarantee_letters');
        Schema::dropIfExists('hsp_ina_cbg_tariffs');
    }
};
