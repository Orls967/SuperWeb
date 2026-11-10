<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Lot Valas untuk kalkulasi FIFO/Identifikasi Khusus & Revaluasi PSAK 10
        Schema::create('trs_fx_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_id', 40)->default('HOLDING')->index();
            $table->string('currency', 3)->index();
            $table->date('lot_date');
            $table->bigInteger('original_amount'); // minor units
            $table->bigInteger('remaining_amount');
            $table->unsignedBigInteger('original_rate_num');
            $table->unsignedBigInteger('original_rate_den')->default(1);
            $table->bigInteger('original_cost_idr');
            $table->unsignedBigInteger('current_carrying_rate_num');
            $table->unsignedBigInteger('current_carrying_rate_den')->default(1);
            $table->bigInteger('current_carrying_idr');
            $table->uuid('last_revaluation_id')->nullable();
            $table->string('status', 20)->default('active')->index(); // active, exhausted
            $table->string('source_ref', 80)->nullable();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('trs_currencies')->cascadeOnDelete();
        });

        // 2. Audit Trail State Machine Kontrak Lindung Nilai (Forward Contract Histories)
        Schema::create('trs_forward_contract_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('forward_contract_id')->index();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 40);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('forward_contract_id')->references('id')->on('trs_forward_contracts')->cascadeOnDelete();
        });

        // 3. Hasil Pencocokan Rekonsiliasi Bank 3-Tahap
        Schema::create('trs_bank_reconciliation_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('statement_id')->index();
            $table->string('match_type', 32); // exact, fuzzy_tolerance, subset_many_to_one, manual
            $table->bigInteger('matched_amount');
            $table->json('ledger_entry_ids')->nullable();
            $table->unsignedBigInteger('ledger_tx_id')->nullable();
            $table->unsignedBigInteger('matched_by')->nullable();
            $table->string('notes', 255)->nullable();
            $table->json('match_certificate')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('statement_id')->references('id')->on('trs_bank_statements')->cascadeOnDelete();
        });

        // 4. Pos Rekonsiliasi Bank Belum Terselesaikan (Unmatched queue)
        Schema::create('trs_bank_reconciliation_unmatched', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('statement_id')->index();
            $table->string('reason', 60);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending, escalated, resolved
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->timestamps();

            $table->foreign('statement_id')->references('id')->on('trs_bank_statements')->cascadeOnDelete();
        });

        // 5. Penarikan Fasilitas Kredit & Pengujian Covenant DER
        Schema::create('trs_credit_facility_drawdowns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('facility_id')->index();
            $table->string('entity_id', 40)->default('HOLDING');
            $table->date('draw_date');
            $table->bigInteger('amount_idr');
            $table->unsignedInteger('covenant_der_permil');
            $table->unsignedInteger('max_der_permil');
            $table->bigInteger('verified_debt_idr');
            $table->bigInteger('verified_equity_idr');
            $table0 = $table->unsignedBigInteger('ledger_tx_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('facility_id')->references('id')->on('trs_credit_facilities')->cascadeOnDelete();
        });

        // 6. Eksekusi Cash Pooling Sweeps (Zero-Balancing / Target Balance)
        Schema::create('trs_cash_pool_sweeps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pool_id')->index();
            $table->date('sweep_date');
            $table->bigInteger('swept_amount_idr');
            $table->string('direction', 20); // sub_to_header, header_to_sub
            $table->bigInteger('header_balance_before');
            $table->bigInteger('header_balance_after');
            $table->bigInteger('sub_balance_before');
            $table->bigInteger('sub_balance_after');
            $table->unsignedBigInteger('ledger_tx_id')->nullable();
            $table->uuid('intercompany_loan_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('pool_id')->references('id')->on('trs_cash_pools')->cascadeOnDelete();
        });

        // 7. Kas Kecil (Petty Cash Vouchers - Imprest System)
        Schema::create('trs_petty_cash_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bank_account_id')->index();
            $table->string('voucher_number', 50)->unique();
            $table->date('voucher_date');
            $table->bigInteger('amount');
            $table->string('payee', 100);
            $table->string('expense_account_code', 64);
            $table->string('description', 255);
            $table->string('status', 20)->default('submitted'); // submitted, approved, reimbursed, rejected
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('ledger_tx_id')->nullable();
            $table->timestamps();

            $table->foreign('bank_account_id')->references('id')->on('trs_bank_accounts')->cascadeOnDelete();
        });

        // 8. Kolom pengayaan pada tabel yang sudah ada
        Schema::table('trs_forward_contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('creator_id')->nullable()->after('currency');
            $table->unsignedBigInteger('contract_rate_numerator')->default(1)->after('notional_foreign_amount');
            $table->unsignedBigInteger('contract_rate_denominator')->default(1)->after('contract_rate_numerator');
            $table->unsignedBigInteger('spot_rate_numerator')->nullable()->after('contract_rate_denominator');
            $table->unsignedBigInteger('spot_rate_denominator')->nullable()->after('spot_rate_numerator');
            $table->uuid('rolled_to_contract_id')->nullable()->after('status');
            $table->uuid('rolled_from_contract_id')->nullable()->after('rolled_to_contract_id');
            $table->bigInteger('settlement_amount_idr')->nullable()->after('rolled_from_contract_id');
            $table->bigInteger('settlement_gain_loss_idr')->nullable()->after('settlement_amount_idr');
            $table->string('cancellation_fee_payer', 40)->nullable()->after('settlement_gain_loss_idr');
            $table->bigInteger('cancellation_fee_idr')->nullable()->after('cancellation_fee_payer');
            $table->text('rejection_reason')->nullable()->after('cancellation_fee_idr');
        });

        Schema::table('trs_credit_facilities', function (Blueprint $table) {
            $table->unsignedInteger('interest_rate_permil')->default(85)->after('drawn_amount_idr');
            $table->unsignedInteger('max_der_permil')->default(2500)->after('interest_rate_permil');
            $table->string('covenant_frequency', 20)->default('monthly')->after('max_der_permil');
            $table->string('entity_id', 40)->default('HOLDING')->after('facility_code');
        });

        Schema::table('trs_bank_statements', function (Blueprint $table) {
            $table->string('reconciliation_status', 20)->default('unreconciled')->after('is_reconciled');
            $table->string('bank_code', 20)->nullable()->after('reference_no');
            $table->json('raw_payload')->nullable()->after('description');
        });

        Schema::table('trs_revaluations', function (Blueprint $table) {
            $table->uuid('reverse_of')->nullable()->after('status');
            $table->boolean('is_reversed')->default(false)->after('reverse_of');
            $table->timestamp('reversed_at')->nullable()->after('is_reversed');
            $table->json('certificate')->nullable()->after('reversed_at');
        });

        Schema::table('trs_bank_accounts', function (Blueprint $table) {
            $table->string('entity_id', 40)->default('HOLDING')->after('account_number');
            $table->bigInteger('imprest_limit')->default(0)->after('balance');
        });

        Schema::table('trs_cash_forecasts', function (Blueprint $table) {
            $table->unsignedSmallInteger('history_cycles')->default(0)->after('scenario');
            $table->bigInteger('ols_intercept')->default(0)->after('history_cycles');
            $table->bigInteger('ols_slope')->default(0)->after('ols_intercept');
            $table->json('seasonal_indices')->nullable()->after('ols_slope');
            $table->json('forecast_values')->nullable()->after('seasonal_indices');
            $table->json('certificate')->nullable()->after('forecast_values');
        });
    }

    public function down(): void
    {
        Schema::table('trs_cash_forecasts', function (Blueprint $table) {
            $table->dropColumn(['history_cycles', 'ols_intercept', 'ols_slope', 'seasonal_indices', 'forecast_values', 'certificate']);
        });

        Schema::table('trs_bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['entity_id', 'imprest_limit']);
        });

        Schema::table('trs_revaluations', function (Blueprint $table) {
            $table->dropColumn(['reverse_of', 'is_reversed', 'reversed_at', 'certificate']);
        });

        Schema::table('trs_bank_statements', function (Blueprint $table) {
            $table->dropColumn(['reconciliation_status', 'bank_code', 'raw_payload']);
        });

        Schema::table('trs_credit_facilities', function (Blueprint $table) {
            $table->dropColumn(['interest_rate_permil', 'max_der_permil', 'covenant_frequency', 'entity_id']);
        });

        Schema::table('trs_forward_contracts', function (Blueprint $table) {
            $table->dropColumn([
                'creator_id', 'contract_rate_numerator', 'contract_rate_denominator',
                'spot_rate_numerator', 'spot_rate_denominator', 'rolled_to_contract_id',
                'rolled_from_contract_id', 'settlement_amount_idr', 'settlement_gain_loss_idr',
                'cancellation_fee_payer', 'cancellation_fee_idr', 'rejection_reason',
            ]);
        });

        Schema::dropIfExists('trs_petty_cash_vouchers');
        Schema::dropIfExists('trs_cash_pool_sweeps');
        Schema::dropIfExists('trs_credit_facility_drawdowns');
        Schema::dropIfExists('trs_bank_reconciliation_unmatched');
        Schema::dropIfExists('trs_bank_reconciliation_matches');
        Schema::dropIfExists('trs_forward_contract_histories');
        Schema::dropIfExists('trs_fx_lots');
    }
};
