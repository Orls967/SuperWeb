<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 49.1 Master negara, pelabuhan, Incoterms 2020, HS codes
        Schema::create('trd_countries', function (Blueprint $table) {
            $table->string('code', 2)->primary(); // ISO-2: ID, SG, CN, US, JP
            $table->string('name', 80);
            $table->string('currency_code', 3)->default('USD');
            $table->boolean('has_fta')->default(false);
            $table->timestamps();
        });

        Schema::create('trd_ports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique(); // IDTPP, SGSIN, etc.
            $table->string('name', 100);
            $table->string('country_code', 2);
            $table->string('type', 16)->default('seaport')->comment('seaport, airport');
            $table->timestamps();

            $table->foreign('country_code')->references('code')->on('trd_countries')->cascadeOnDelete();
        });

        Schema::create('trd_incoterms', function (Blueprint $table) {
            $table->string('code', 3)->primary(); // EXW, FOB, CIF, DDP, CIP, CFR, FCA
            $table->string('name', 80);
            $table->string('risk_transfer_point', 120);
            $table->string('cost_responsibility', 120);
            $table->timestamps();
        });

        Schema::create('trd_hs_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('hs_code', 20)->unique();
            $table->string('description', 255);
            $table->decimal('base_duty_rate_percent', 5, 2)->default(5.00);
            $table->decimal('fta_preferential_rate_percent', 5, 2)->default(0.00);
            $table->boolean('is_lartas')->default(false);
            $table->string('lartas_permit_required', 80)->nullable();
            $table->timestamps();
        });

        // 49.2 Order Ekspor & Pengakuan Pendapatan
        Schema::create('trd_export_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 40)->unique();
            $table->string('buyer_name', 120);
            $table->string('destination_country_code', 2);
            $table->uuid('destination_port_id');
            $table->string('incoterm_code', 3);
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('total_foreign_amount');
            $table->bigInteger('total_functional_idr')->default(0);
            $table->string('peb_number', 40)->nullable()->comment('Pemberitahuan Ekspor Barang');
            $table->string('status', 24)->default('draft')->comment('draft, proforma, confirmed, shipped, risk_transferred, completed, cancelled');
            $table->timestamp('risk_transferred_at')->nullable();
            $table->timestamps();

            $table->foreign('destination_country_code')->references('code')->on('trd_countries')->cascadeOnDelete();
            $table->foreign('destination_port_id')->references('id')->on('trd_ports')->cascadeOnDelete();
            $table->foreign('incoterm_code')->references('code')->on('trd_incoterms')->cascadeOnDelete();
        });

        // 49.3 Order Impor & Kalkulasi Bea Cukai PIB (BM, PPN, PPh 22, Landed Cost)
        Schema::create('trd_import_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 40)->unique();
            $table->string('supplier_name', 120);
            $table->string('origin_country_code', 2);
            $table->uuid('origin_port_id');
            $table->string('incoterm_code', 3);
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('cif_foreign_amount');
            $table->bigInteger('cif_idr');
            $table->bigInteger('customs_duty_bm_idr')->default(0);
            $table->bigInteger('import_vat_ppn_idr')->default(0);
            $table->bigInteger('import_tax_pph22_idr')->default(0);
            $table->bigInteger('total_landed_cost_idr')->default(0);
            $table->string('pib_number', 40)->nullable()->comment('Pemberitahuan Impor Barang');
            $table->boolean('coo_verified')->default(false);
            $table->string('status', 24)->default('ordered')->comment('ordered, in_transit, customs_cleared, received');
            $table->timestamps();

            $table->foreign('origin_country_code')->references('code')->on('trd_countries')->cascadeOnDelete();
            $table->foreign('origin_port_id')->references('id')->on('trd_ports')->cascadeOnDelete();
            $table->foreign('incoterm_code')->references('code')->on('trd_incoterms')->cascadeOnDelete();
        });

        // 49.4 Dokumen Perdagangan (Certificate of Origin, Fumigasi, Phytosanitary)
        Schema::create('trd_trade_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('documentable_type', 80);
            $table->uuid('documentable_id');
            $table->string('doc_type', 32)->comment('coo_form_e, fumigation, phytosanitary, halal, bl_awb');
            $table->string('certificate_number', 80);
            $table->string('issuing_authority', 120);
            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id'], 'trd_doc_index');
        });

        // 49.6 Pelacakan Lintas Batas (Cross-Border Tracking Hash-Chain)
        Schema::create('trd_shipment_legs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_reference_no', 40);
            $table->string('leg_stage', 24)->comment('origin, port_loading, transit, port_discharge, customs, destination');
            $table->string('location_name', 120);
            $table->string('notes', 255)->nullable();
            $table->string('previous_hash', 64)->nullable();
            $table->string('hash', 64);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index('order_reference_no');
        });

        // 49.7 Sengketa & Klaim Dagang Internasional
        Schema::create('trd_trade_disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('dispute_number', 40)->unique();
            $table->string('order_reference_no', 40);
            $table->string('claim_reason', 40)->comment('damaged, shortage, delay, quality_mismatch');
            $table->bigInteger('claim_amount_idr');
            $table->bigInteger('insurance_payout_idr')->default(0);
            $table->string('status', 24)->default('submitted')->comment('submitted, under_investigation, settled, rejected');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trd_trade_disputes');
        Schema::dropIfExists('trd_shipment_legs');
        Schema::dropIfExists('trd_trade_documents');
        Schema::dropIfExists('trd_import_orders');
        Schema::dropIfExists('trd_export_orders');
        Schema::dropIfExists('trd_hs_codes');
        Schema::dropIfExists('trd_incoterms');
        Schema::dropIfExists('trd_ports');
        Schema::dropIfExists('trd_countries');
    }
};
