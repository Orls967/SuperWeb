<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 52.1 Master Entitas Legal Grup
        Schema::create('ic_entities', function (Blueprint $table) {
            $table->id();
            $table->string('entity_code', 32)->unique();
            $table->string('legal_name', 128);
            $table->string('country_code', 2)->default('ID');
            $table->string('functional_currency', 3)->default('IDR');
            $table->text('tax_id_npwp');
            $table->unsignedInteger('ownership_percent_permille')->default(100000);
            $table->boolean('is_parent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 52.2 Transfer Pricing Rules & PMK/OECD Compliance
        Schema::create('ic_transfer_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code', 32)->unique();
            $table->string('product_category', 64);
            $table->string('tp_method', 10); // CUP, CPM, RPM, TNMM
            $table->decimal('min_arms_length_margin_percent', 5, 2)->default(6.00);
            $table->decimal('max_arms_length_margin_percent', 5, 2)->default(14.00);
            $table->integer('min_margin_bps')->default(600);
            $table->integer('max_margin_bps')->default(1400);
            $table->integer('median_margin_bps')->default(1000);
            $table->string('benchmark_industry_source')->default('OECD_STAT_2026');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ic_tp_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('ic_transfer_pricing_rules')->cascadeOnDelete();
            $table->string('comparator_name', 128);
            $table->string('financial_year', 4);
            $table->integer('operating_margin_bps');
            $table->integer('gross_margin_bps');
            $table->string('source_dataset', 64)->default('OECD_AMNE_2026');
            $table->timestamps();
        });

        // 52.1 Transaksi Antar-Entitas Grup (Mirror Transactions & IC Loans)
        Schema::create('ic_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 32)->unique();
            $table->string('selling_entity', 64);
            $table->string('buying_entity', 64);
            $table->foreignId('selling_entity_id')->nullable()->constrained('ic_entities');
            $table->foreignId('buying_entity_id')->nullable()->constrained('ic_entities');
            $table->string('description');
            $table->string('currency', 3)->default('IDR');
            $table->bigInteger('amount_idr');
            $table->bigInteger('amount_minor')->default(0);
            $table->bigInteger('amount_functional_idr')->default(0);
            $table->string('sales_invoice_ref', 32);
            $table->string('purchase_bill_ref', 32);
            $table->foreignId('tp_rule_id')->nullable()->constrained('ic_transfer_pricing_rules');
            $table->string('status', 32)->default('draft');
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('eliminated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions');
            $table->timestamps();
        });

        Schema::create('ic_transaction_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('ic_transactions')->cascadeOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('reason')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('ic_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_agreement_number', 32)->unique();
            $table->string('lender_entity', 64);
            $table->string('borrower_entity', 64);
            $table->foreignId('lender_entity_id')->nullable()->constrained('ic_entities');
            $table->foreignId('borrower_entity_id')->nullable()->constrained('ic_entities');
            $table->string('currency', 3)->default('IDR');
            $table->bigInteger('principal_idr');
            $table->bigInteger('principal_minor')->default(0);
            $table->integer('interest_rate_bps')->default(600);
            $table->integer('spread_bps')->default(50);
            $table->integer('withholding_tax_bps')->default(1500);
            $table->decimal('arms_length_interest_rate', 5, 2)->default(6.50);
            $table->date('start_date');
            $table->date('due_date');
            $table->bigInteger('repaid_principal_idr')->default(0);
            $table->bigInteger('repaid_principal_minor')->default(0);
            $table->string('status', 20)->default('active');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions');
            $table->timestamps();
        });

        Schema::create('ic_loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('ic_loans')->cascadeOnDelete();
            $table->integer('installment_number');
            $table->date('due_date');
            $table->bigInteger('beginning_balance_minor');
            $table->bigInteger('principal_due_minor');
            $table->bigInteger('interest_due_minor');
            $table->bigInteger('tax_withheld_minor');
            $table->bigInteger('net_interest_minor');
            $table->bigInteger('ending_balance_minor');
            $table->string('payment_status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions');
            $table->timestamps();
        });

        Schema::create('ic_tp_true_ups', function (Blueprint $table) {
            $table->id();
            $table->string('fiscal_year', 4);
            $table->foreignId('entity_id')->nullable()->constrained('ic_entities');
            $table->string('category', 64);
            $table->integer('actual_margin_bps');
            $table->integer('target_median_bps');
            $table->bigInteger('adjustment_amount_idr');
            $table->string('status', 20)->default('pending');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions');
            $table->timestamps();
        });

        Schema::create('ic_tp_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 32); // LOCAL_FILE, MASTER_FILE
            $table->string('fiscal_year', 4);
            $table->foreignId('entity_id')->nullable()->constrained('ic_entities');
            $table->text('far_analysis_json');
            $table->text('benchmark_summary_json');
            $table->string('status', 20)->default('draft');
            $table->string('sha256_hash', 64)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        // 52.3 Perpindahan Aset & Logistik
        Schema::create('ic_asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_code', 32)->unique();
            $table->string('asset_code', 32);
            $table->foreignId('source_entity_id')->nullable()->constrained('ic_entities');
            $table->foreignId('target_entity_id')->nullable()->constrained('ic_entities');
            $table->bigInteger('net_book_value_idr');
            $table->bigInteger('accumulated_depreciation_idr');
            $table->bigInteger('transfer_price_idr');
            $table->bigInteger('gain_loss_idr')->default(0);
            $table->string('status', 20)->default('transferred');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions');
            $table->timestamps();
        });

        Schema::create('ic_stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_code', 32)->unique();
            $table->foreignId('source_entity_id')->nullable()->constrained('ic_entities');
            $table->foreignId('target_entity_id')->nullable()->constrained('ic_entities');
            $table->string('customs_peb_number', 32)->nullable();
            $table->string('customs_pib_number', 32)->nullable();
            $table->bigInteger('cif_value_idr');
            $table->bigInteger('import_duty_idr')->default(0);
            $table->bigInteger('import_vat_idr')->default(0);
            $table->string('status', 20)->default('delivered');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions');
            $table->timestamps();
        });

        // 52.1 Cost Sharing / Management Fee
        Schema::create('ic_cost_sharing_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('agreement_code', 32)->unique();
            $table->string('agreement_name', 128);
            $table->foreignId('provider_entity_id')->nullable()->constrained('ic_entities');
            $table->string('allocation_basis', 20); // HEADCOUNT, REVENUE
            $table->string('service_category', 64);
            $table->integer('markup_bps')->default(500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ic_cost_sharing_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained('ic_cost_sharing_agreements')->cascadeOnDelete();
            $table->string('period', 7);
            $table->foreignId('beneficiary_entity_id')->nullable()->constrained('ic_entities');
            $table->unsignedInteger('allocation_ratio_permille');
            $table->bigInteger('allocated_cost_idr');
            $table->bigInteger('markup_amount_idr');
            $table->bigInteger('total_billed_idr');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions');
            $table->timestamps();
        });

        // 52.4 Eliminasi Konsolidasi & Translasi
        Schema::create('ic_elimination_entries', function (Blueprint $table) {
            $table->id();
            $table->string('elimination_code', 32)->unique();
            $table->string('period', 7); // YYYY-MM
            $table->string('elimination_type', 32);
            $table->string('selling_entity', 64)->nullable();
            $table->string('buying_entity', 64)->nullable();
            $table->foreignId('selling_entity_id')->nullable()->constrained('ic_entities');
            $table->foreignId('buying_entity_id')->nullable()->constrained('ic_entities');
            $table->string('debit_account');
            $table->string('credit_account');
            $table->bigInteger('amount_idr');
            $table->string('status', 20)->default('posted');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions');
            $table->timestamps();
        });

        Schema::create('ic_unrealized_inventory_profits', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);
            $table->foreignId('selling_entity_id')->nullable()->constrained('ic_entities');
            $table->foreignId('buying_entity_id')->nullable()->constrained('ic_entities');
            $table->string('product_code', 64);
            $table->bigInteger('inventory_balance_idr');
            $table->unsignedInteger('unrealized_profit_ratio_permille');
            $table->bigInteger('unrealized_profit_idr');
            $table->string('status', 20)->default('eliminated');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions');
            $table->timestamps();
        });

        // 52.5 Non-Controlling Interest (NCI)
        Schema::create('ic_subsidiary_nci', function (Blueprint $table) {
            $table->id();
            $table->string('subsidiary_name', 64)->unique();
            $table->foreignId('subsidiary_entity_id')->nullable()->constrained('ic_entities');
            $table->decimal('parent_ownership_percent', 5, 2)->default(80.00);
            $table->decimal('nci_ownership_percent', 5, 2)->default(20.00);
            $table->unsignedInteger('parent_ownership_permille')->default(80000);
            $table->unsignedInteger('nci_ownership_permille')->default(20000);
            $table->bigInteger('net_income_idr')->default(0);
            $table->bigInteger('nci_share_net_income_idr')->default(0);
            $table->string('period', 7)->default('2026-10');
            $table->timestamps();
        });

        Schema::create('ic_nci_dividends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidiary_entity_id')->nullable()->constrained('ic_entities');
            $table->date('declaration_date');
            $table->bigInteger('total_dividend_idr');
            $table->bigInteger('nci_dividend_idr');
            $table->string('status', 20)->default('paid');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ic_nci_dividends');
        Schema::dropIfExists('ic_subsidiary_nci');
        Schema::dropIfExists('ic_unrealized_inventory_profits');
        Schema::dropIfExists('ic_elimination_entries');
        Schema::dropIfExists('ic_cost_sharing_allocations');
        Schema::dropIfExists('ic_cost_sharing_agreements');
        Schema::dropIfExists('ic_stock_transfers');
        Schema::dropIfExists('ic_asset_transfers');
        Schema::dropIfExists('ic_tp_documents');
        Schema::dropIfExists('ic_tp_true_ups');
        Schema::dropIfExists('ic_loan_schedules');
        Schema::dropIfExists('ic_loans');
        Schema::dropIfExists('ic_transaction_histories');
        Schema::dropIfExists('ic_transactions');
        Schema::dropIfExists('ic_tp_benchmarks');
        Schema::dropIfExists('ic_transfer_pricing_rules');
        Schema::dropIfExists('ic_entities');
    }
};
