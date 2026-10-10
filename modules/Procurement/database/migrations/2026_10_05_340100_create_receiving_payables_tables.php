<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 34.1 Goods Receipt (GRN): parsial, toleransi, lot/batch ──────
        Schema::create('prc_receiving_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('GRN/{ENT}/YYYY-NNNNN (26.8)');
            $table->uuid('po_id');
            $table->string('status', 24)->default('open')->comment('open, inspected, quarantined, accepted, rejected, closed');
            $table->date('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('po_id')->references('id')->on('prc_purchase_orders')->cascadeOnDelete();
            $table->index(['status', 'received_at']);
        });

        Schema::create('prc_receiving_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('grn_id');
            $table->unsignedBigInteger('po_line_id');
            $table->string('description', 300);
            $table->unsignedInteger('ordered_qty')->default(0);
            $table->unsignedInteger('received_qty')->default(0);
            $table->unsignedInteger('accepted_qty')->default(0);
            $table->unsignedInteger('rejected_qty')->default(0);
            $table->string('lot_number', 60)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('warehouse_code', 40)->nullable()->comment('Kode lokasi penyimpanan (InventoryService)');
            $table->unsignedBigInteger('product_id')->nullable()->comment('store_products.id — penempatan stok');
            $table->timestamps();

            $table->foreign('grn_id')->references('id')->on('prc_receiving_reports')->cascadeOnDelete();
        });

        // ── 34.2 Inspeksi (hook QMS Fase 39) + retur (debit note) ────────
        Schema::create('prc_inspections', function (Blueprint $table) {
            $table->id();
            $table->uuid('grn_id');
            $table->unsignedBigInteger('receiving_line_id')->nullable();
            $table->string('result', 16)->default('pending')->comment('pending, passed, failed');
            $table->boolean('quarantine')->default(false)->comment('Kuarantina menunggu inspeksi/QMS Fase 39');
            $table->text('findings')->nullable();
            $table->foreignId('inspected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('grn_id')->references('id')->on('prc_receiving_reports')->cascadeOnDelete();
        });

        Schema::create('prc_supplier_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grn_id');
            $table->string('number', 40)->unique()->comment('RTR/{ENT}/YYYY-NNNNN');
            $table->unsignedInteger('qty')->default(1);
            $table->bigInteger('amount_idr')->default(0)->comment('Nilai retur (debit note)');
            $table->string('reason', 300);
            $table->string('status', 16)->default('issued')->comment('issued, credited, settled');
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->foreign('grn_id')->references('id')->on('prc_receiving_reports')->cascadeOnDelete();
        });

        // ── 34.3 Invoice pemasok + 3-way match ────────────────────────────
        Schema::create('prc_supplier_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 60)->unique()->comment('Nomor invoice vendor (unik per pemasok)');
            $table->uuid('supplier_id');
            $table->uuid('po_id')->nullable();
            $table->string('status', 24)->default('pending')->comment('pending, matched, held, approved, paid, void');
            $table->bigInteger('invoice_amount_idr')->default(0);
            $table->bigInteger('matched_amount_idr')->default(0);
            $table->bigInteger('variance_idr')->default(0)->comment('Selisih vs PO/GRN (PPV)');
            $table->unsignedInteger('price_tolerance_pct')->default(2);
            $table->unsignedInteger('qty_tolerance_pct')->default(5);
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
            $table->foreign('po_id')->references('id')->on('prc_purchase_orders')->nullOnDelete();
            $table->index(['status', 'due_date']);
        });

        Schema::create('prc_three_way_matches', function (Blueprint $table) {
            $table->id();
            $table->uuid('invoice_id');
            $table->uuid('grn_id')->nullable();
            $table->uuid('po_id')->nullable();
            $table->string('result', 16)->default('pending')->comment('matched, price_hold, qty_hold');
            $table->bigInteger('po_amount_idr')->default(0);
            $table->bigInteger('grn_amount_idr')->default(0);
            $table->bigInteger('invoice_amount_idr')->default(0);
            $table->bigInteger('price_variance_idr')->default(0);
            $table->unsignedInteger('qty_variance_pct')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('prc_supplier_invoices')->cascadeOnDelete();
        });

        // ── 34.4 Akuntansi AP: GR/IR, PPN/PPh, selisih harga ──────────────
        Schema::create('prc_ap_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('invoice_id')->nullable();
            $table->uuid('supplier_id');
            $table->string('kind', 32)->comment('gr_ir, ap, ppn_input, pph23_withheld, ppv, advance, credit_memo');
            $table->bigInteger('amount_idr');
            $table->string('direction', 16)->default('debit')->comment('debit, credit');
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->string('reference', 80)->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
            $table->index(['kind', 'invoice_id']);
        });

        // ── 34.5 Jadwal pembayaran & batch payment run ─────────────────────
        Schema::create('prc_payment_batches', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique()->comment('BATCH/{ENT}/YYYY-NNNNN');
            $table->string('status', 24)->default('draft')->comment('draft, pending_approval, approved, processing, completed, failed');
            $table->bigInteger('total_amount_idr')->default(0);
            $table->unsignedInteger('item_count')->default(0);
            $table->date('scheduled_date')->nullable();
            $table->text('proof')->nullable()->comment('Bukti potong / ref transfer');
            $table->string('approval_id', 64)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('prc_payment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->uuid('invoice_id');
            $table->bigInteger('amount_idr')->default(0);
            $table->bigInteger('early_discount_idr')->default(0)->comment('Diskon pembayaran dini (simulasi)');
            $table->string('status', 16)->default('pending')->comment('pending, paid, failed, skipped');
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('prc_payment_batches')->cascadeOnDelete();
            $table->unique(['batch_id', 'invoice_id']);
        });

        // ── 34.6 Uang muka, kredit memo, pelunasan sebagian ───────────────
        Schema::create('prc_supplier_advances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->bigInteger('amount_idr')->default(0);
            $table->bigInteger('used_amount_idr')->default(0);
            $table->string('status', 16)->default('open')->comment('open, consumed, refunded');
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
            $table->index(['status']);
        });

        Schema::create('prc_credit_memos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->uuid('invoice_id')->nullable();
            $table->string('number', 40)->unique();
            $table->bigInteger('amount_idr')->default(0);
            $table->string('reason', 300);
            $table->string('status', 16)->default('issued')->comment('issued, applied, void');
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('sup_suppliers')->cascadeOnDelete();
        });

        // ── 34.7 Landed cost: alokasi ke nilai persediaan ──────────────────
        Schema::create('prc_landed_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('po_id');
            $table->string('kind', 32)->comment('freight, insurance, duty, handling, other');
            $table->bigInteger('amount_idr')->default(0);
            $table->string('allocation_method', 24)->default('value')->comment('value, weight, qty');
            $table->string('status', 16)->default('estimated')->comment('estimated, actual, allocated');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('po_id')->references('id')->on('prc_purchase_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prc_landed_costs');
        Schema::dropIfExists('prc_credit_memos');
        Schema::dropIfExists('prc_supplier_advances');
        Schema::dropIfExists('prc_payment_items');
        Schema::dropIfExists('prc_payment_batches');
        Schema::dropIfExists('prc_ap_entries');
        Schema::dropIfExists('prc_three_way_matches');
        Schema::dropIfExists('prc_supplier_invoices');
        Schema::dropIfExists('prc_supplier_returns');
        Schema::dropIfExists('prc_inspections');
        Schema::dropIfExists('prc_receiving_lines');
        Schema::dropIfExists('prc_receiving_reports');
    }
};
