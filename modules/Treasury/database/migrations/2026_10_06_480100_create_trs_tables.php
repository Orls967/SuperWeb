<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 48.1 Master mata uang & kurs ber-versi immutable
        Schema::create('trs_currencies', function (Blueprint $table) {
            $table->string('code', 3)->primary();
            $table->string('name', 60);
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('minor_units')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('trs_exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->date('rate_date');
            $table->string('rate_type', 16)->default('spot')->comment('spot, middle, tax');
            $table->unsignedBigInteger('rate_numerator')->comment('Kurs integer scaled 1e6');
            $table->unsignedBigInteger('rate_denominator')->default(1000000);
            $table->string('source', 40)->default('bi_simulated');
            $table->timestamps();

            $table->foreign('from_currency')->references('code')->on('trs_currencies')->cascadeOnDelete();
            $table->foreign('to_currency')->references('code')->on('trs_currencies')->cascadeOnDelete();
            $table->unique(['from_currency', 'to_currency', 'rate_date', 'rate_type'], 'trs_rate_unique');
        });

        // 48.3 Revaluasi kurs akhir periode
        Schema::create('trs_revaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('period', 8)->comment('YYYY-MM');
            $table->string('currency', 3);
            $table->bigInteger('foreign_balance')->comment('Minor unit');
            $table->bigInteger('book_functional_idr');
            $table->bigInteger('revalued_functional_idr');
            $table->bigInteger('gain_loss_idr');
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->string('status', 16)->default('completed');
            $table->timestamps();

            $table->unique(['period', 'currency']);
        });

        // 48.4 Rekening bank perusahaan, kas, rekonsiliasi & kas kecil
        Schema::create('trs_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('account_number', 40)->unique();
            $table->string('bank_name', 80);
            $table->string('currency', 3)->default('IDR');
            $table->bigInteger('balance')->default(0);
            $table->boolean('is_petty_cash')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('trs_currencies')->cascadeOnDelete();
        });

        Schema::create('trs_bank_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bank_account_id');
            $table->date('transaction_date');
            $table->string('reference_no', 80);
            $table->bigInteger('amount');
            $table->string('description', 255);
            $table->boolean('is_reconciled')->default(false);
            $table->unsignedBigInteger('matched_ledger_tx_id')->nullable();
            $table->timestamps();

            $table->foreign('bank_account_id')->references('id')->on('trs_bank_accounts')->cascadeOnDelete();
        });

        // 48.5 Forecast arus kas 13 minggu
        Schema::create('trs_cash_forecasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scenario', 30)->default('base');
            $table->unsignedTinyInteger('week_number');
            $table->date('start_date');
            $table->bigInteger('projected_inflow_idr')->default(0);
            $table->bigInteger('projected_outflow_idr')->default(0);
            $table->bigInteger('net_cash_flow_idr')->default(0);
            $table->bigInteger('closing_balance_idr')->default(0);
            $table->timestamps();
        });

        // 48.6 Lindung nilai (Forward Contract)
        Schema::create('trs_forward_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_number', 40)->unique();
            $table->string('currency', 3);
            $table->bigInteger('notional_foreign_amount');
            $table->unsignedBigInteger('forward_rate_scaled');
            $table->date('maturity_date');
            $table->bigInteger('mtm_value_idr')->default(0);
            $table->string('status', 16)->default('active')->comment('active, settled, expired');
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('trs_currencies')->cascadeOnDelete();
        });

        // 48.7 Pinjaman & Fasilitas Bank
        Schema::create('trs_credit_facilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('facility_code', 40)->unique();
            $table->string('bank_name', 80);
            $table->bigInteger('credit_limit_idr');
            $table->bigInteger('drawn_amount_idr')->default(0);
            $table->decimal('interest_rate_percent', 5, 2)->default(8.50);
            $table->decimal('max_debt_equity_ratio', 4, 2)->default(2.50);
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        // 48.8 Cash Pooling & Intercompany Loans
        Schema::create('trs_cash_pools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pool_name', 80);
            $table->uuid('header_account_id');
            $table->uuid('sub_account_id');
            $table->bigInteger('target_balance_idr')->default(0);
            $table->bigInteger('last_swept_amount_idr')->default(0);
            $table->timestamp('last_swept_at')->nullable();
            $table->timestamps();

            $table->foreign('header_account_id')->references('id')->on('trs_bank_accounts')->cascadeOnDelete();
            $table->foreign('sub_account_id')->references('id')->on('trs_bank_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trs_cash_pools');
        Schema::dropIfExists('trs_credit_facilities');
        Schema::dropIfExists('trs_forward_contracts');
        Schema::dropIfExists('trs_cash_forecasts');
        Schema::dropIfExists('trs_bank_statements');
        Schema::dropIfExists('trs_bank_accounts');
        Schema::dropIfExists('trs_revaluations');
        Schema::dropIfExists('trs_exchange_rates');
        Schema::dropIfExists('trs_currencies');
    }
};
