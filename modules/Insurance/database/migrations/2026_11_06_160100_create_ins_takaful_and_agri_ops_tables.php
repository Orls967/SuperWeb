<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Dana Tabarru' & Ujrah Syariah (ins_tk_funds)
        Schema::create('ins_tk_funds', function (Blueprint $table) {
            $table->id();
            $table->string('fund_code', 32)->unique();
            $table->string('fund_type', 32); // TABARRU, OPERATOR_UJRAH
            $table->string('name', 100);
            $table->unsignedBigInteger('balance_idr')->default(0);
            $table->unsignedBigInteger('total_contributions_idr')->default(0);
            $table->unsignedBigInteger('total_claims_paid_idr')->default(0);
            $table->unsignedBigInteger('contingency_reserve_idr')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['fund_type', 'is_active']);
        });

        // 2. Peserta Takaful (ins_tk_participants)
        Schema::create('ins_tk_participants', function (Blueprint $table) {
            $table->id();
            $table->uuid('participant_party_id');
            $table->string('policy_number', 60)->unique();
            $table->foreignId('fund_id')->constrained('ins_tk_funds')->restrictOnDelete();
            $table->string('plan_type', 40); // FAMILY_TAKAFUL, GENERAL_TAKAFUL
            $table->unsignedBigInteger('contribution_annual_idr');
            $table->unsignedSmallInteger('tabarru_portion_bps')->default(7000); // 70% dana tabarru
            $table->unsignedSmallInteger('ujrah_portion_bps')->default(3000); // 30% ujrah wakalah
            $table->unsignedBigInteger('sum_covered_idr');
            $table->string('status', 32)->default('active'); // active, lapsed, surrendered, claimed
            $table->date('effective_date');
            $table->date('expiry_date');
            $table->timestamps();

            $table->index('participant_party_id');
            $table->index(['fund_id', 'status']);
        });

        // 3. Distribusi Surplus Underwriting Takaful (ins_tk_surplus_distributions)
        Schema::create('ins_tk_surplus_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('ins_tk_funds')->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('net_contributions_idr');
            $table->unsignedBigInteger('net_claims_idr');
            $table->unsignedBigInteger('technical_reserve_idr');
            $table->unsignedBigInteger('gross_surplus_idr');
            $table->unsignedBigInteger('participant_share_idr');
            $table->unsignedBigInteger('contingency_reserve_idr');
            $table->unsignedBigInteger('mudharabah_operator_idr');
            $table->string('calculation_certificate', 128);
            $table->dateTime('distributed_at');
            $table->timestamps();

            $table->unique(['fund_id', 'fiscal_year']);
        });

        // 4. Polis Asuransi Pertanian Berbasis Indeks Cuaca/NDVI (ins_agri_policies)
        Schema::create('ins_agri_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 60)->unique();
            $table->uuid('farmer_party_id');
            $table->string('commodity', 40); // PADI, JAGUNG, KELAPA_SAWIT, KEDELAI
            $table->unsignedInteger('farm_area_ha'); // Luas lahan dalam satuan Ha
            $table->unsignedBigInteger('sum_insured_idr');
            $table->unsignedBigInteger('premium_annual_idr');
            $table->string('index_type', 32); // RAINFALL, DROUGHT, NDVI_INDEX
            $table->unsignedInteger('trigger_threshold_val'); // Nilai trigger (misal curah hujan mm atau ndvi score)
            $table->string('status', 32)->default('active');
            $table->date('effective_date');
            $table->date('expiry_date');
            $table->timestamps();

            $table->index('farmer_party_id');
            $table->index(['commodity', 'status']);
        });

        // 5. Klaim Asuransi Pertanian & Offset Pinjaman (ins_agri_claims)
        Schema::create('ins_agri_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number', 60)->unique();
            $table->foreignId('policy_id')->constrained('ins_agri_policies')->restrictOnDelete();
            $table->unsignedInteger('actual_index_val');
            $table->unsignedBigInteger('gross_claim_idr');
            $table->unsignedBigInteger('loan_offset_amount_idr');
            $table->unsignedBigInteger('net_farmer_payout_idr');
            $table->string('status', 32)->default('approved'); // submitted, approved, settled, rejected
            $table->dateTime('adjudicated_at')->nullable();
            $table->timestamps();

            $table->index(['policy_id', 'status']);
        });

        // 6. Polis Asuransi Mikro Massal (ins_micro_policies)
        Schema::create('ins_micro_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 60)->unique();
            $table->uuid('member_party_id');
            $table->string('product_type', 40); // TRANSIT_DAILY, GADGET_MICRO, RIDER_PROTECT
            $table->unsignedBigInteger('daily_premium_idr');
            $table->unsignedBigInteger('sum_insured_idr');
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->string('status', 32)->default('active');
            $table->dateTime('issued_at');
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index('member_party_id');
            $table->index(['product_type', 'status']);
        });

        // 7. Command Center Solvency & Portfolio Snapshots (ins_command_snapshots)
        Schema::create('ins_command_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->unsignedBigInteger('gross_written_premium_idr');
            $table->unsignedBigInteger('net_earned_premium_idr');
            $table->unsignedBigInteger('claims_incurred_idr');
            $table->unsignedSmallInteger('loss_ratio_bps'); // misal 6250 = 62.5%
            $table->unsignedSmallInteger('solvency_rbc_bps'); // misal 24500 = 245% (OJK min 120%)
            $table->unsignedBigInteger('total_technical_reserve_idr');
            $table->unsignedBigInteger('reinsurance_recoverable_idr');
            $table->timestamps();
        });

        // 8. Keluhan Nasabah & Kepatuhan Regulasi (ins_compliance_grievances)
        Schema::create('ins_compliance_grievances', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 60)->unique();
            $table->string('policy_ref', 60);
            $table->uuid('complainant_party_id');
            $table->string('category', 40); // CLAIM_DENIAL, DELAYED_PAYOUT, PREMIUM_DISPUTE
            $table->text('grievance_text');
            $table->date('sla_deadline');
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->string('status', 32)->default('open'); // open, in_investigation, resolved, escalated_ojk
            $table->timestamps();

            $table->index('complainant_party_id');
            $table->index(['status', 'sla_deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_compliance_grievances');
        Schema::dropIfExists('ins_command_snapshots');
        Schema::dropIfExists('ins_micro_policies');
        Schema::dropIfExists('ins_agri_claims');
        Schema::dropIfExists('ins_agri_policies');
        Schema::dropIfExists('ins_tk_surplus_distributions');
        Schema::dropIfExists('ins_tk_participants');
        Schema::dropIfExists('ins_tk_funds');
    }
};
