<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add geocoordinates to pharmacy branches if not present
        if (Schema::hasTable('hsp_pharmacy_branches')) {
            Schema::table('hsp_pharmacy_branches', function (Blueprint $table): void {
                if (! Schema::hasColumn('hsp_pharmacy_branches', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->default(-6.2088000)->after('city');
                    $table->decimal('longitude', 10, 7)->default(106.8456000)->after('latitude');
                    $table->string('full_address', 255)->nullable()->after('longitude');
                    $table->boolean('has_cold_chain_storage')->default(true)->after('is_active');
                }
            });
        }

        // Medicine courier deliveries with cold-chain and e-POD tracking
        if (! Schema::hasTable('hsp_medicine_courier_deliveries')) {
            Schema::create('hsp_medicine_courier_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->string('delivery_code', 32)->unique();
                $table->foreignId('epharmacy_order_id')->constrained('hsp_epharmacy_orders')->cascadeOnDelete();
                $table->string('courier_service', 64)->default('HOSPITAL_EXPRESS');
                $table->string('courier_name', 128)->nullable();
                $table->string('courier_phone', 32)->nullable();
                $table->string('recipient_name', 128);
                $table->string('recipient_phone', 32);
                $table->text('destination_address');
                $table->decimal('destination_latitude', 10, 7)->nullable();
                $table->decimal('destination_longitude', 10, 7)->nullable();
                $table->boolean('requires_cold_chain')->default(false);
                $table->decimal('min_allowed_temp_c', 4, 1)->default(2.0);
                $table->decimal('max_allowed_temp_c', 4, 1)->default(8.0);
                $table->decimal('last_recorded_temp_c', 4, 1)->nullable();
                $table->boolean('temperature_breached')->default(false);
                $table->string('delivery_status', 32)->default('PREPARING'); // PREPARING, IN_TRANSIT, DELIVERED, QUARANTINED_BREACH, FAILED
                $table->string('pod_recipient_signature_hash', 64)->nullable();
                $table->string('pod_recipient_nik', 32)->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->index(['delivery_status', 'requires_cold_chain'], 'hsp_med_deliv_status_cold_idx');
            });
        }

        // BPJS SISRUTE & Integrated Hospital Referral Bridging
        if (! Schema::hasTable('hsp_bpjs_referral_bridging')) {
            Schema::create('hsp_bpjs_referral_bridging', function (Blueprint $table): void {
                $table->id();
                $table->string('referral_code', 32)->unique();
                $table->foreignId('patient_id')->constrained('hsp_patients')->cascadeOnDelete();
                $table->unsignedBigInteger('tele_consult_id')->nullable();
                $table->unsignedBigInteger('encounter_id')->nullable();
                $table->string('referral_direction', 16)->default('OUTBOUND'); // OUTBOUND, INBOUND
                $table->string('referral_type', 32)->default('HORIZONTAL'); // HORIZONTAL, VERTICAL_UP, VERTICAL_DOWN
                $table->string('sisrute_reference_number', 64)->nullable()->unique();
                $table->string('origin_faskes_code', 32);
                $table->string('origin_faskes_name', 128);
                $table->string('destination_faskes_code', 32);
                $table->string('destination_faskes_name', 128);
                $table->string('diagnosis_icd10', 32);
                $table->text('clinical_summary');
                $table->string('transportation_type', 32)->default('AMBULANCE'); // AMBULANCE, INDEPENDENT
                $table->string('target_polyclinic_or_ward', 64);
                $table->string('approval_status', 32)->default('REQUESTED'); // REQUESTED, ACCEPTED, REJECTED, EXPIRED
                $table->timestamp('bridged_at')->nullable();
                $table->timestamps();

                $table->index(['referral_direction', 'approval_status'], 'hsp_bpjs_ref_dir_stat_idx');
            });
        }

        // Drug-Drug Interaction and Contraindication CDS Rules
        if (! Schema::hasTable('hsp_drug_interaction_rules')) {
            Schema::create('hsp_drug_interaction_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('drug_a_code', 32);
                $table->string('drug_a_name', 128);
                $table->string('drug_b_code', 32);
                $table->string('drug_b_name', 128);
                $table->string('interaction_severity', 32)->default('MAJOR'); // CONTRAINDICATED, MAJOR, MODERATE, MINOR
                $table->text('clinical_mechanism');
                $table->text('recommendation');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['drug_a_code', 'drug_b_code'], 'hsp_drug_interact_pair_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_drug_interaction_rules');
        Schema::dropIfExists('hsp_bpjs_referral_bridging');
        Schema::dropIfExists('hsp_medicine_courier_deliveries');

        if (Schema::hasTable('hsp_pharmacy_branches')) {
            Schema::table('hsp_pharmacy_branches', function (Blueprint $table): void {
                if (Schema::hasColumn('hsp_pharmacy_branches', 'latitude')) {
                    $table->dropColumn(['latitude', 'longitude', 'full_address', 'has_cold_chain_storage']);
                }
            });
        }
    }
};
