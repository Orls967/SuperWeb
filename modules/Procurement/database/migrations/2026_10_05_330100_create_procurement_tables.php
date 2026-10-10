<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 33.6 Pusat biaya & anggaran (encumbrance) ─────────────────────
        Schema::create('prc_budget_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->unsignedBigInteger('annual_budget_idr')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('prc_budget_encumbrances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_center_id');
            $table->string('source_type', 32)->comment('pr, po, amendment');
            $table->unsignedBigInteger('source_id');
            $table->bigInteger('amount_idr');
            $table->string('status', 16)->default('active')->comment('active, released, consumed');
            $table->timestamps();

            $table->foreign('budget_center_id')->references('id')->on('prc_budget_centers')->cascadeOnDelete();
            $table->unique(['source_type', 'source_id']);
            $table->index(['budget_center_id', 'status']);
        });

        // ── 33.1 Purchase Requisition ────────────────────────────────────
        Schema::create('prc_requisitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('PR/{ENT}/YYYY-NNNNN (26.8)');
            $table->string('title', 200);
            $table->text('notes')->nullable();
            $table->string('source', 32)->default('manual')->comment('manual, mrp, reorder_point');
            $table->unsignedBigInteger('budget_center_id')->nullable();
            $table->uuid('legal_entity_id')->nullable();
            $table->string('status', 24)->default('draft')->comment('draft, pending_approval, approved, rejected, ordered, cancelled');
            $table->string('approval_id', 64)->nullable();
            $table->bigInteger('total_estimated_idr')->default(0);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('budget_center_id')->references('id')->on('prc_budget_centers')->nullOnDelete();
            $table->index(['status', 'created_at']);
        });

        Schema::create('prc_requisition_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('requisition_id');
            $table->string('description', 300);
            $table->unsignedBigInteger('supplier_item_id')->nullable()->comment('sup_items.id (tautan, tanpa FK lintas modul)');
            $table->unsignedInteger('qty')->default(1);
            $table->string('unit', 32)->default('pcs');
            $table->bigInteger('estimated_unit_price_idr')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();

            $table->foreign('requisition_id')->references('id')->on('prc_requisitions')->cascadeOnDelete();
        });

        // ── 33.2 RFQ multi-pemasok ───────────────────────────────────────
        Schema::create('prc_rfqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique();
            $table->uuid('requisition_id')->nullable();
            $table->string('title', 200);
            $table->string('status', 24)->default('open')->comment('open, evaluating, awarded, closed, cancelled');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('requisition_id')->references('id')->on('prc_requisitions')->nullOnDelete();
            $table->index(['status', 'closes_at']);
        });

        Schema::create('prc_rfq_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('rfq_id');
            $table->uuid('supplier_id');
            $table->string('status', 16)->default('invited')->comment('invited, quoted, declined');
            $table->timestamps();

            $table->foreign('rfq_id')->references('id')->on('prc_rfqs')->cascadeOnDelete();
            $table->unique(['rfq_id', 'supplier_id']);
        });

        Schema::create('prc_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('rfq_id');
            $table->uuid('supplier_id');
            $table->bigInteger('total_price_idr')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->string('selection_reason')->nullable()->comment('Alasan tercatat saat memilih penawaran');
            $table->timestamps();

            $table->foreign('rfq_id')->references('id')->on('prc_rfqs')->cascadeOnDelete();
            $table->unique(['rfq_id', 'supplier_id']);
        });

        // ── 33.3 Tender: segel penawaran (hash), evaluasi berbobot ────────
        Schema::create('prc_tenders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique();
            $table->string('title', 200);
            $table->string('type', 16)->default('closed')->comment('closed, open');
            $table->string('status', 24)->default('bidding')->comment('bidding, opened, evaluated, awarded, cancelled');
            $table->timestamp('bids_open_at');
            $table->timestamp('bids_close_at');
            $table->json('addendums')->nullable()->comment('Daftar addendum (versi dokumen tender)');
            $table->json('criteria')->comment('Bobot evaluasi: harga, lead_time, skor (persen)');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'bids_close_at']);
        });

        // Segel penawaran: peserta mengirim hash sebelum tenggat (blind bidding).
        Schema::create('prc_tender_bids', function (Blueprint $table) {
            $table->id();
            $table->uuid('tender_id');
            $table->uuid('supplier_id');
            $table->string('seal_hash', 64)->comment('SHA-256 segel penawaran sebelum tenggat');
            $table->timestamp('sealed_at');
            $table->json('offer')->nullable()->comment('Isi penawaran; hanya terbaca setelah open');
            $table->timestamp('opened_at')->nullable();
            $table->decimal('total_score', 8, 4)->nullable()->comment('Hasil evaluasi berbobot');
            $table->boolean('is_winner')->default(false);
            $table->string('notes')->nullable();
            $table->string('approval_id', 64)->nullable();
            $table->timestamps();

            $table->foreign('tender_id')->references('id')->on('prc_tenders')->cascadeOnDelete();
            $table->unique(['tender_id', 'supplier_id']);
        });

        // ── 33.4 Purchase Order ───────────────────────────────────────────
        Schema::create('prc_purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('PO/{ENT}/YYYY-NNNNN (26.8)');
            $table->uuid('supplier_id');
            $table->uuid('requisition_id')->nullable();
            $table->uuid('rfq_id')->nullable();
            $table->uuid('tender_id')->nullable();
            $table->uuid('contract_id')->nullable()->comment('Kontrak kerangka (Fase 28/29)');
            $table->uuid('blanket_po_id')->nullable()->comment('Induk blanket PO (call-off rujuk ke sini)');
            $table->string('title', 200);
            $table->string('kind', 24)->default('standard')->comment('standard, blanket, call_off, import');
            $table->string('status', 24)->default('draft')->comment('draft, sent, acknowledged, partially_received, received, closed, cancelled');
            $table->unsignedInteger('version')->default(1);
            $table->string('currency', 3)->default('IDR');
            $table->bigInteger('total_amount')->default(0);
            $table->bigInteger('received_amount')->default(0);
            $table->date('expected_date')->nullable();
            $table->date('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('budget_center_id')->nullable();
            $table->unsignedBigInteger('encumbrance_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('budget_center_id')->references('id')->on('prc_budget_centers')->nullOnDelete();
            $table->index(['status', 'expected_date']);
            $table->index('supplier_id');
        });

        Schema::create('prc_po_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('po_id');
            $table->string('description', 300);
            $table->unsignedInteger('qty')->default(1);
            $table->string('unit', 32)->default('pcs');
            $table->bigInteger('unit_price')->default(0);
            $table->bigInteger('line_total')->default(0);
            $table->unsignedInteger('received_qty')->default(0);
            $table->timestamps();

            $table->foreign('po_id')->references('id')->on('prc_purchase_orders')->cascadeOnDelete();
        });

        // Jejak versi PO (33.4) — perubahan via approval menghasilkan versi baru.
        Schema::create('prc_po_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('po_id');
            $table->unsignedInteger('version');
            $table->string('change_summary', 500);
            $table->json('snapshot')->comment('Snapshot penuh PO pada versi ini');
            $table->string('status', 16)->default('recorded')->comment('recorded, approved, rejected');
            $table->string('approval_id', 64)->nullable();
            $table->timestamps();

            $table->foreign('po_id')->references('id')->on('prc_purchase_orders')->cascadeOnDelete();
            $table->unique(['po_id', 'version']);
        });

        // ── 33.5 PO impor: Incoterm, pelabuhan, landed cost estimasi ──────
        Schema::create('prc_import_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('po_id')->unique();
            $table->string('incoterm', 10)->default('FOB')->comment('EXW, FOB, CFR, CIF, DAP, DDP (simulasi)');
            $table->string('currency', 3)->default('USD');
            $table->decimal('fx_rate', 18, 6)->default(1)->comment('Kurs ke IDR (simulasi Fase 48)');
            $table->string('origin_port', 100)->nullable();
            $table->string('destination_port', 100)->nullable();
            $table->bigInteger('freight_estimate_idr')->default(0);
            $table->bigInteger('insurance_estimate_idr')->default(0);
            $table->bigInteger('duty_estimate_idr')->default(0);
            $table->bigInteger('landed_cost_estimate_idr')->default(0)->comment('Nilai barang + freight + asuransi + bea');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('po_id')->references('id')->on('prc_purchase_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prc_import_profiles');
        Schema::dropIfExists('prc_po_versions');
        Schema::dropIfExists('prc_po_lines');
        Schema::dropIfExists('prc_purchase_orders');
        Schema::dropIfExists('prc_tender_bids');
        Schema::dropIfExists('prc_tenders');
        Schema::dropIfExists('prc_quotes');
        Schema::dropIfExists('prc_rfq_invitations');
        Schema::dropIfExists('prc_rfqs');
        Schema::dropIfExists('prc_requisition_lines');
        Schema::dropIfExists('prc_requisitions');
        Schema::dropIfExists('prc_budget_encumbrances');
        Schema::dropIfExists('prc_budget_centers');
    }
};
