<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Perluasan ef_budgets untuk State Machine & Four-Eyes Principle
        Schema::table('ef_budgets', function (Blueprint $table) {
            $table->string('status', 20)->default('DRAFT')->after('control_type');
            $table->foreignId('created_by')->nullable()->constrained('users')->after('status');
            $table->foreignId('approved_by')->nullable()->constrained('users')->after('created_by');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->after('approved_by');
        });

        // 2. Riwayat Transisi Siklus Anggaran
        Schema::create('ef_budget_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('ef_budgets')->cascadeOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('reason')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 3. Fasilitas Kredit Sindikasi & Obligasi Korporat
        Schema::create('ef_syndicated_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('facility_code', 32)->unique();
            $table->string('facility_name', 128);
            $table->string('facility_type', 32); // TERM_LOAN, REVOLVING, OFFSHORE
            $table->string('lead_arranger', 128);
            $table->string('currency', 3)->default('IDR');
            $table->bigInteger('total_commitment_minor');
            $table->bigInteger('drawn_amount_minor')->default(0);
            $table->bigInteger('repaid_amount_minor')->default(0);
            $table->integer('interest_rate_bps')->default(650);
            $table->integer('spread_bps')->default(225);
            $table->date('start_date');
            $table->date('maturity_date');
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions');
            $table->timestamps();
        });

        // 4. Riwayat Evaluasi Covenant Utang Sindikasi (D7)
        Schema::create('ef_debt_covenant_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('ef_syndicated_facilities')->cascadeOnDelete();
            $table->string('evaluation_period', 16);
            $table->integer('dscr_permille');
            $table->integer('der_permille');
            $table->integer('iscr_permille');
            $table->integer('current_ratio_permille');
            $table->boolean('is_compliant')->default(true);
            $table->string('sha256_signature', 64);
            $table->text('certificate_payload_json');
            $table->foreignId('evaluated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 5. Header Siklus Konsolidasi Trial Balance Multi-Entitas (D8)
        Schema::create('ef_consolidation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code', 32)->unique();
            $table->string('period', 7); // YYYY-MM
            $table->string('reporting_currency', 3)->default('IDR');
            $table->integer('total_entities_count');
            $table->bigInteger('total_debit_minor');
            $table->bigInteger('total_credit_minor');
            $table->bigInteger('elimination_total_minor')->default(0);
            $table->boolean('is_balanced')->default(true);
            $table->string('sha256_signature', 64);
            $table->string('status', 20)->default('published');
            $table->foreignId('executed_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 6. Rincian Akun Neraca Saldo Konsolidasi
        Schema::create('ef_consolidation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidation_run_id')->constrained('ef_consolidation_runs')->cascadeOnDelete();
            $table->string('entity_code', 32);
            $table->string('canonical_account_code', 32);
            $table->string('account_name', 128);
            $table->bigInteger('local_debit_minor')->default(0);
            $table->bigInteger('local_credit_minor')->default(0);
            $table->bigInteger('elimination_debit_minor')->default(0);
            $table->bigInteger('elimination_credit_minor')->default(0);
            $table->bigInteger('consolidated_net_minor')->default(0);
            $table->timestamps();
        });

        // 7. Metrik Optimalisasi Modal Kerja Grup (54.5)
        Schema::create('ef_working_capital_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('entity_code', 32);
            $table->string('period', 7);
            $table->bigInteger('revenue_minor');
            $table->bigInteger('cogs_minor');
            $table->bigInteger('ar_balance_minor');
            $table->bigInteger('inventory_balance_minor');
            $table->bigInteger('ap_balance_minor');
            $table->integer('dso_days');
            $table->integer('dio_days');
            $table->integer('dpo_days');
            $table->integer('ccc_days');
            $table->string('efficiency_tier', 20)->default('NORMAL');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ef_working_capital_metrics');
        Schema::dropIfExists('ef_consolidation_entries');
        Schema::dropIfExists('ef_consolidation_runs');
        Schema::dropIfExists('ef_debt_covenant_evaluations');
        Schema::dropIfExists('ef_syndicated_facilities');
        Schema::dropIfExists('ef_budget_histories');

        Schema::table('ef_budgets', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['ledger_transaction_id']);
            $table->dropColumn(['status', 'created_by', 'approved_by', 'ledger_transaction_id']);
        });
    }
};
