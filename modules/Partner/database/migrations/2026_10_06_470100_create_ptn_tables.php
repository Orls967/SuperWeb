<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 47.1–47.9 Modul Partner (ptn_): jenis mitra, siklus hidup, due diligence, JBP, revenue sharing, co-selling, HKI, exit.
return new class extends Migration
{
    public function up(): void
    {
        // 47.1 Mitra & jenis: strategic, tech, channel, franchise, jv, research, csr.
        Schema::create('ptn_partners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 180);
            $table->string('kind', 32)->comment('strategic, tech, channel, franchise, jv, research, csr');
            $table->uuid('party_id')->nullable()->comment('pty_parties.id');
            $table->unsignedBigInteger('owner_user_id')->nullable()->comment('Portal role partner');
            $table->string('status', 24)->default('prospect')->comment('prospect, due_diligence, negotiation, active, review, exit');
            $table->string('contract_ref', 60)->nullable()->comment('ctr_contracts.id');
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'kind']);
        });

        // 47.2 Due diligence & skor risiko.
        Schema::create('ptn_due_diligences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('partner_id');
            $table->unsignedSmallInteger('score')->default(0);
            $table->json('checklist')->nullable()->comment('legal, financial, reputation, esg, sanction');
            $table->string('status', 24)->default('pending')->comment('pending, approved, rejected');
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('ptn_partners')->cascadeOnDelete();
        });

        // 47.3 Joint Business Plan (JBP): sasaran, KPI, anggaran, PIC.
        Schema::create('ptn_joint_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('partner_id');
            $table->string('title', 160);
            $table->string('period', 8)->comment('YYYY atau YYYY-QX');
            $table->unsignedBigInteger('budget_idr')->default(0);
            $table->unsignedBigInteger('target_revenue_idr')->default(0);
            $table->string('internal_pic', 100)->nullable();
            $table->string('partner_pic', 100)->nullable();
            $table->json('kpi_targets')->nullable();
            $table->string('status', 24)->default('draft')->comment('draft, active, completed, cancelled');
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('ptn_partners')->cascadeOnDelete();
        });

        // 47.4 Revenue / Profit Sharing generik.
        Schema::create('ptn_revenue_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('partner_id');
            $table->string('period', 8)->comment('YYYY-MM');
            $table->unsignedBigInteger('gross_revenue_idr')->default(0);
            $table->unsignedBigInteger('deductible_cost_idr')->default(0);
            $table->unsignedBigInteger('net_base_idr')->default(0);
            $table->decimal('share_rate_percent', 6, 2)->default(0);
            $table->unsignedBigInteger('share_amount_idr')->default(0);
            $table->string('status', 24)->default('draft')->comment('draft, approved, paid');
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('ptn_partners')->cascadeOnDelete();
            $table->unique(['partner_id', 'period']);
        });

        // 47.5 Co-selling & Marketplace B2B.
        Schema::create('ptn_cosell_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('partner_id');
            $table->string('title', 160);
            $table->string('category', 40);
            $table->unsignedBigInteger('price_idr')->default(0);
            $table->decimal('referral_fee_percent', 5, 2)->default(0);
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('ptn_partners')->cascadeOnDelete();
        });

        // 47.7 SLA, Scorecard & Review Berkala (QBR).
        Schema::create('ptn_scorecards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('partner_id');
            $table->string('period', 8);
            $table->unsignedSmallInteger('score')->default(0)->comment('0-100');
            $table->decimal('sla_compliance_percent', 5, 2)->default(100.00);
            $table->unsignedBigInteger('penalties_idr')->default(0);
            $table->text('remediation_plan')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('ptn_partners')->cascadeOnDelete();
            $table->unique(['partner_id', 'period']);
        });

        // 47.8 HKI & Aset Bersama (merek, paten, hak cipta).
        Schema::create('ptn_intellectual_properties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('partner_id');
            $table->string('type', 32)->comment('trademark, patent, copyright, industrial_design');
            $table->string('registration_number', 80);
            $table->string('name', 160);
            $table->date('registered_at');
            $table->date('expires_at')->nullable();
            $table->string('status', 24)->default('valid');
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('ptn_partners')->cascadeOnDelete();
        });

        // 47.9 Exit & Terminasi.
        Schema::create('ptn_exit_transitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('partner_id');
            $table->string('reason', 300);
            $table->unsignedBigInteger('final_settlement_idr')->default(0);
            $table->text('asset_split_summary')->nullable();
            $table->date('exit_date');
            $table->string('status', 24)->default('completed');
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('ptn_partners')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptn_exit_transitions');
        Schema::dropIfExists('ptn_intellectual_properties');
        Schema::dropIfExists('ptn_scorecards');
        Schema::dropIfExists('ptn_cosell_listings');
        Schema::dropIfExists('ptn_revenue_shares');
        Schema::dropIfExists('ptn_joint_plans');
        Schema::dropIfExists('ptn_due_diligences');
        Schema::dropIfExists('ptn_partners');
    }
};
