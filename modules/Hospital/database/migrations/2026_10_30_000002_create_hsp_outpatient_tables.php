<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hsp_branch_inventory_lots') && ! Schema::hasColumn('hsp_branch_inventory_lots', 'unit_cost_idr')) {
            Schema::table('hsp_branch_inventory_lots', function (Blueprint $table): void {
                $table->bigInteger('unit_cost_idr')->default(1000);
            });
        }

        if (! Schema::hasTable('hsp_polyclinics')) {
            Schema::create('hsp_polyclinics', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 16)->unique();
                $table->string('name', 128);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hsp_doctors')) {
            Schema::create('hsp_doctors', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('polyclinic_id')->constrained('hsp_polyclinics')->cascadeOnDelete();
                $table->string('doctor_code', 32)->unique();
                $table->string('sip_number', 64)->nullable();
                $table->string('specialization', 128)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hsp_tariff_versions')) {
            Schema::create('hsp_tariff_versions', function (Blueprint $table): void {
                $table->id();
                $table->string('service_code', 32);
                $table->string('name', 128);
                $table->string('category', 32);
                $table->bigInteger('amount_idr');
                $table->date('effective_from');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['service_code', 'effective_from'], 'idx_hsp_tariffs_srv_eff');
            });
        }

        if (! Schema::hasTable('hsp_queues')) {
            Schema::create('hsp_queues', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('polyclinic_id')->constrained('hsp_polyclinics')->cascadeOnDelete();
                $table->foreignId('doctor_id')->nullable()->constrained('hsp_doctors')->nullOnDelete();
                $table->date('queue_date');
                $table->integer('queue_number');
                $table->string('queue_code', 32)->unique();
                $table->string('status', 32)->default('WAITING');
                $table->timestamps();

                $table->unique(['polyclinic_id', 'queue_date', 'queue_number'], 'uniq_hsp_q_poli_date_num');
                $table->index(['queue_date', 'status'], 'idx_hsp_q_date_stat');
            });
        }

        if (! Schema::hasTable('hsp_outpatient_encounters')) {
            Schema::create('hsp_outpatient_encounters', function (Blueprint $table): void {
                $table->id();
                $table->string('encounter_code', 32)->unique();
                $table->foreignId('patient_id')->constrained('hsp_patients')->cascadeOnDelete();
                $table->foreignId('queue_id')->constrained('hsp_queues')->cascadeOnDelete();
                $table->foreignId('polyclinic_id')->constrained('hsp_polyclinics')->cascadeOnDelete();
                $table->foreignId('doctor_id')->constrained('hsp_doctors')->cascadeOnDelete();
                $table->string('payment_method', 16)->default('CASH');
                $table->string('bpjs_card_number', 32)->nullable();
                $table->string('status', 32)->default('REGISTERED');
                $table->integer('systolic_bp')->nullable();
                $table->integer('diastolic_bp')->nullable();
                $table->integer('pulse_rate')->nullable();
                $table->integer('respiratory_rate')->nullable();
                $table->decimal('body_temp_c', 4, 1)->nullable();
                $table->text('encrypted_doctor_notes')->nullable();
                $table->text('encrypted_icd10_code')->nullable();
                $table->dateTime('examined_at')->nullable();
                $table->timestamps();

                $table->index(['patient_id', 'status'], 'idx_hsp_enc_pat_stat');
                $table->index(['polyclinic_id', 'status'], 'idx_hsp_enc_pol_stat');
            });
        }

        if (! Schema::hasTable('hsp_outpatient_prescriptions')) {
            Schema::create('hsp_outpatient_prescriptions', function (Blueprint $table): void {
                $table->id();
                $table->string('prescription_code', 32)->unique();
                $table->foreignId('encounter_id')->constrained('hsp_outpatient_encounters')->cascadeOnDelete();
                $table->foreignId('doctor_id')->constrained('hsp_doctors')->cascadeOnDelete();
                $table->string('drug_code', 32);
                $table->string('drug_name', 128);
                $table->integer('quantity');
                $table->string('dosage_instructions', 128)->nullable();
                $table->bigInteger('unit_price_idr')->default(0);
                $table->bigInteger('subtotal_idr')->default(0);
                $table->string('status', 32)->default('PRESCRIBED');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hsp_outpatient_dispensings')) {
            Schema::create('hsp_outpatient_dispensings', function (Blueprint $table): void {
                $table->id();
                $table->string('dispensing_code', 32)->unique();
                $table->foreignId('prescription_id')->constrained('hsp_outpatient_prescriptions')->cascadeOnDelete();
                $table->foreignId('lot_id')->constrained('hsp_branch_inventory_lots')->cascadeOnDelete();
                $table->foreignId('dispensed_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->integer('quantity');
                $table->bigInteger('unit_cost_idr');
                $table->bigInteger('total_cogs_idr');
                $table->dateTime('dispensed_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hsp_outpatient_invoices')) {
            Schema::create('hsp_outpatient_invoices', function (Blueprint $table): void {
                $table->id();
                $table->string('invoice_code', 32)->unique();
                $table->foreignId('encounter_id')->constrained('hsp_outpatient_encounters')->cascadeOnDelete();
                $table->bigInteger('doctor_fee_idr')->default(0);
                $table->bigInteger('medication_fee_idr')->default(0);
                $table->bigInteger('total_amount_idr')->default(0);
                $table->string('payment_method', 16)->default('CASH');
                $table->string('ina_cbg_package_code', 32)->nullable();
                $table->bigInteger('ina_cbg_tariff_idr')->nullable();
                $table->string('status', 32)->default('PENDING');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hsp_outpatient_payments')) {
            Schema::create('hsp_outpatient_payments', function (Blueprint $table): void {
                $table->id();
                $table->string('payment_code', 32)->unique();
                $table->foreignId('invoice_id')->constrained('hsp_outpatient_invoices')->cascadeOnDelete();
                $table->foreignId('cashier_user_id')->constrained('users')->cascadeOnDelete();
                $table->bigInteger('amount_idr');
                $table->bigInteger('cash_received_idr');
                $table->bigInteger('change_given_idr')->default(0);
                $table->string('payment_method', 16)->default('CASH');
                $table->string('idempotency_key', 64)->unique();
                $table->dateTime('paid_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hsp_bpjs_claims')) {
            Schema::create('hsp_bpjs_claims', function (Blueprint $table): void {
                $table->id();
                $table->string('claim_code', 32)->unique();
                $table->foreignId('invoice_id')->constrained('hsp_outpatient_invoices')->cascadeOnDelete();
                $table->string('ina_cbg_code', 32);
                $table->bigInteger('ina_cbg_tariff_idr');
                $table->bigInteger('hospital_tariff_idr');
                $table->bigInteger('variance_amount_idr')->default(0);
                $table->bigInteger('patient_copay_amount_idr')->default(0);
                $table->string('status', 32)->default('SUBMITTED');
                $table->string('rejection_reason', 255)->nullable();
                $table->dateTime('adjudicated_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_bpjs_claims');
        Schema::dropIfExists('hsp_outpatient_payments');
        Schema::dropIfExists('hsp_outpatient_invoices');
        Schema::dropIfExists('hsp_outpatient_dispensings');
        Schema::dropIfExists('hsp_outpatient_prescriptions');
        Schema::dropIfExists('hsp_outpatient_encounters');
        Schema::dropIfExists('hsp_queues');
        Schema::dropIfExists('hsp_tariff_versions');
        Schema::dropIfExists('hsp_doctors');
        Schema::dropIfExists('hsp_polyclinics');
    }
};
