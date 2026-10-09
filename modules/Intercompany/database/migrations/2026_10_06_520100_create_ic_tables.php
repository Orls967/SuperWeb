<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 52.1 Transaksi Antar-Entitas Grup (Mirror Transactions & IC Loans)
        Schema::create('ic_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 32)->unique();
            $table->string('selling_entity', 64);
            $table->string('buying_entity', 64);
            $table->string('description');
            $table->string('currency', 3)->default('IDR');
            $table->bigInteger('amount_idr');
            $table->string('sales_invoice_ref', 32);
            $table->string('purchase_bill_ref', 32);
            $table->string('status', 20)->default('matched'); // matched, settled, disputed
            $table->timestamps();
        });

        Schema::create('ic_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_agreement_number', 32)->unique();
            $table->string('lender_entity', 64);
            $table->string('borrower_entity', 64);
            $table->bigInteger('principal_idr');
            $table->decimal('arms_length_interest_rate', 5, 2)->default(6.50);
            $table->date('start_date');
            $table->date('due_date');
            $table->bigInteger('repaid_principal_idr')->default(0);
            $table->string('status', 20)->default('active'); // active, settled
            $table->timestamps();
        });

        // 52.2 Transfer Pricing Engine & PMK/OECD Compliance
        Schema::create('ic_transfer_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code', 32)->unique();
            $table->string('product_category');
            $table->string('tp_method', 10); // CUP, CPM, RPM, TNMM
            $table->decimal('min_arms_length_margin_percent', 5, 2);
            $table->decimal('max_arms_length_margin_percent', 5, 2);
            $table->string('benchmark_industry_source')->default('OECD_STAT_2026');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 52.4 Eliminasi Konsolidasi & Translasi
        Schema::create('ic_elimination_entries', function (Blueprint $table) {
            $table->id();
            $table->string('elimination_code', 32)->unique();
            $table->string('period', 7); // YYYY-MM
            $table->string('elimination_type', 32); // RECIPROCAL_AR_AP, REVENUE_EXPENSE, UNREALIZED_INVENTORY_PROFIT
            $table->string('debit_account');
            $table->string('credit_account');
            $table->bigInteger('amount_idr');
            $table->string('status', 20)->default('posted');
            $table->timestamps();
        });

        // 52.5 Non-Controlling Interest (NCI)
        Schema::create('ic_subsidiary_nci', function (Blueprint $table) {
            $table->id();
            $table->string('subsidiary_name', 64)->unique();
            $table->decimal('parent_ownership_percent', 5, 2);
            $table->decimal('nci_ownership_percent', 5, 2);
            $table->bigInteger('net_income_idr')->default(0);
            $table->bigInteger('nci_share_net_income_idr')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ic_subsidiary_nci');
        Schema::dropIfExists('ic_elimination_entries');
        Schema::dropIfExists('ic_transfer_pricing_rules');
        Schema::dropIfExists('ic_loans');
        Schema::dropIfExists('ic_transactions');
    }
};
