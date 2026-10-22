<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hsp_tele_consult_notes')) {
            Schema::create('hsp_tele_consult_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('consultation_id')->constrained('hsp_tele_consults')->cascadeOnDelete();
                $table->text('subjective_encrypted')->nullable();
                $table->text('objective_encrypted')->nullable();
                $table->text('assessment_encrypted')->nullable();
                $table->text('plan_encrypted')->nullable();
                $table->string('icd10_diagnosis_code', 32)->nullable();
                $table->string('signed_doctor_token', 64)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hsp_epharmacy_order_items')) {
            Schema::create('hsp_epharmacy_order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')->constrained('hsp_epharmacy_orders')->cascadeOnDelete();
                $table->string('item_code', 32);
                $table->string('item_name', 128);
                $table->string('dosage_form', 64)->nullable();
                $table->integer('quantity');
                $table->bigInteger('unit_price_idr');
                $table->bigInteger('subtotal_idr');
                $table->text('instructions')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'item_code']);
            });
        }

        if (! Schema::hasTable('hsp_lab_partner_contracts')) {
            Schema::create('hsp_lab_partner_contracts', function (Blueprint $table): void {
                $table->id();
                $table->string('partner_name', 128);
                $table->string('contract_code', 64)->unique();
                $table->decimal('discount_percentage', 5, 2)->default(0);
                $table->integer('max_turnaround_hours')->default(24);
                $table->string('sample_pickup_daily_cutoff', 16)->nullable();
                $table->string('cold_chain_carrier_sla', 64)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_lab_partner_contracts');
        Schema::dropIfExists('hsp_epharmacy_order_items');
        Schema::dropIfExists('hsp_tele_consult_notes');
    }
};
