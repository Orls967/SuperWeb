<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Proyek Pembangkit / Penyerapan Kredit Karbon
        if (! Schema::hasTable('egy_carbon_projects')) {
            Schema::create('egy_carbon_projects', function (Blueprint $table) {
                $table->id();
                $table->string('project_code', 50)->unique();
                $table->string('project_name', 120);
                $table->string('project_type', 40); // forestry_afforestation, solar_farm, biogas_wte, energy_efficiency
                $table->string('standard_registry', 40); // SRN_PPI, VERRA_VCS, GOLD_STANDARD
                $table->string('registry_code', 60)->nullable();
                $table->unsignedBigInteger('developer_party_id');
                $table->unsignedBigInteger('total_certified_credits_kg_co2e')->default(0);
                $table->unsignedBigInteger('available_credits_kg_co2e')->default(0);
                $table->unsignedBigInteger('retired_credits_kg_co2e')->default(0);
                $table->string('status', 30)->default('active');
                $table->timestamps();

                $table->index(['project_type', 'status']);
                $table->index('developer_party_id');
            });
        }

        // 2. Batch Kredit Karbon menurut Vintage Year
        if (! Schema::hasTable('egy_carbon_credit_batches')) {
            Schema::create('egy_carbon_credit_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('carbon_project_id')->constrained('egy_carbon_projects')->cascadeOnDelete();
                $table->string('batch_number', 60)->unique();
                $table->string('vintage_year', 4); // YYYY
                $table->unsignedBigInteger('initial_quantity_kg_co2e');
                $table->unsignedBigInteger('remaining_quantity_kg_co2e');
                $table->unsignedBigInteger('issued_price_minor_per_ton');
                $table->date('verification_date');
                $table->date('expiration_date');
                $table->string('verifier_entity', 100);
                $table->string('certificate_hash', 64);
                $table->string('status', 30)->default('available'); // available, depleted, expired
                $table->timestamps();

                $table->index(['vintage_year', 'status']);
                $table->index(['carbon_project_id', 'status']);
            });
        }

        // 3. Buku Pesanan Perdagangan Karbon Internal (Orderbook)
        if (! Schema::hasTable('egy_carbon_orders')) {
            Schema::create('egy_carbon_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 50)->unique();
                $table->unsignedBigInteger('party_id');
                $table->string('side', 10); // BUY, SELL
                $table->string('vintage_year', 4)->nullable();
                $table->unsignedBigInteger('quantity_kg_co2e');
                $table->unsignedBigInteger('filled_kg_co2e')->default(0);
                $table->unsignedBigInteger('limit_price_minor_per_ton');
                $table->string('status', 30)->default('open'); // open, partially_filled, filled, cancelled, expired
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->timestamps();

                $table->index(['side', 'status', 'limit_price_minor_per_ton']);
                $table->index(['party_id', 'status']);
                $table->index('batch_id');
            });
        }

        // 4. Eksekusi Pencocokan Transaksi Karbon (Trades)
        if (! Schema::hasTable('egy_carbon_trades')) {
            Schema::create('egy_carbon_trades', function (Blueprint $table) {
                $table->id();
                $table->string('trade_number', 50)->unique();
                $table->foreignId('buy_order_id')->constrained('egy_carbon_orders')->cascadeOnDelete();
                $table->foreignId('sell_order_id')->constrained('egy_carbon_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('buyer_party_id');
                $table->unsignedBigInteger('seller_party_id');
                $table->unsignedBigInteger('matched_kg_co2e');
                $table->unsignedBigInteger('execution_price_minor_per_ton');
                $table->unsignedBigInteger('total_amount_idr');
                $table->unsignedBigInteger('platform_fee_idr')->default(0);
                $table->unsignedBigInteger('ledger_transaction_id')->nullable();
                $table->string('settlement_status', 30)->default('settled');
                $table->string('certificate_hash', 64);
                $table->timestamps();

                $table->index(['buyer_party_id', 'seller_party_id']);
                $table->index('ledger_transaction_id');
            });
        }

        // 5. Sertifikat Energi Terbarukan (REC / I-REC Standard)
        if (! Schema::hasTable('egy_rec_certificates')) {
            Schema::create('egy_rec_certificates', function (Blueprint $table) {
                $table->id();
                $table->string('certificate_serial', 60)->unique();
                $table->unsignedBigInteger('generation_asset_id');
                $table->string('energy_source_type', 30); // solar_pv, biogas_wte, micro_hydro
                $table->string('period_month', 7); // YYYY-MM
                $table->string('vintage_year', 4);
                $table->unsignedBigInteger('energy_wh');
                $table->unsignedBigInteger('owner_party_id');
                $table->string('status', 30)->default('minted'); // minted, listed, transferred, retired
                $table->unsignedBigInteger('trading_price_minor')->nullable();
                $table->dateTime('retired_at')->nullable();
                $table->string('retirement_beneficiary', 120)->nullable();
                $table->text('retirement_reason')->nullable();
                $table->string('certificate_hash', 64);
                $table->timestamps();

                $table->index(['owner_party_id', 'status']);
                $table->index(['generation_asset_id', 'period_month']);
            });
        }

        // 6. Deklarasi Pajak / Penyesuaian Emisi Batas Karbon Ekspor (EU CBAM)
        if (! Schema::hasTable('egy_cbam_declarations')) {
            Schema::create('egy_cbam_declarations', function (Blueprint $table) {
                $table->id();
                $table->string('declaration_number', 50)->unique();
                $table->string('export_contract_number', 60);
                $table->string('container_number', 30);
                $table->string('hs_code', 20);
                $table->string('country_destination', 10)->default('EU');
                $table->unsignedBigInteger('exporter_party_id');
                $table->unsignedBigInteger('product_tonnage_kg');
                $table->unsignedBigInteger('specific_emission_factor_bps'); // kg CO2e / ton
                $table->unsignedBigInteger('eu_benchmark_factor_bps');
                $table->unsignedBigInteger('gross_embedded_emissions_kg_co2e');
                $table->unsignedBigInteger('net_payable_emissions_kg_co2e');
                $table->unsignedBigInteger('carbon_price_eur_minor_per_ton');
                $table->unsignedBigInteger('total_cbam_liability_eur_minor');
                $table->unsignedBigInteger('total_cbam_liability_idr');
                $table->unsignedBigInteger('ledger_transaction_id')->nullable();
                $table->string('status', 30)->default('declared'); // declared, audited, paid, cleared
                $table->string('certificate_hash', 64);
                $table->timestamps();

                $table->index(['exporter_party_id', 'status']);
                $table->index('ledger_transaction_id');
            });
        }

        // 7. Marketplace ESG - Daftar Produk & Instrumen Keberlanjutan
        if (! Schema::hasTable('egy_esg_marketplace_listings')) {
            Schema::create('egy_esg_marketplace_listings', function (Blueprint $table) {
                $table->id();
                $table->string('listing_code', 50)->unique();
                $table->string('title', 120);
                $table->string('category', 40); // carbon_offset, rec_bundle, recycled_raw_material, green_lease_perk
                $table->text('description');
                $table->unsignedBigInteger('seller_party_id');
                $table->unsignedBigInteger('price_minor_per_unit');
                $table->string('unit_label', 20); // ton_co2e, mwh, kg, lease_month
                $table->unsignedBigInteger('available_quantity');
                $table->unsignedBigInteger('sold_quantity')->default(0);
                $table->string('status', 30)->default('active'); // active, paused, closed
                $table->timestamps();

                $table->index(['category', 'status']);
                $table->index('seller_party_id');
            });
        }

        // 8. Pesanan & Pembelian Marketplace ESG
        if (! Schema::hasTable('egy_esg_marketplace_orders')) {
            Schema::create('egy_esg_marketplace_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 50)->unique();
                $table->foreignId('listing_id')->constrained('egy_esg_marketplace_listings')->cascadeOnDelete();
                $table->unsignedBigInteger('buyer_party_id');
                $table->unsignedBigInteger('purchased_quantity');
                $table->unsignedBigInteger('unit_price_minor');
                $table->unsignedBigInteger('total_amount_idr');
                $table->unsignedBigInteger('ledger_transaction_id')->nullable();
                $table->string('redemption_code', 60)->nullable();
                $table->string('claim_status', 30)->default('fulfilled'); // fulfilled, claimed, refunded
                $table->string('certificate_hash', 64);
                $table->timestamps();

                $table->index(['buyer_party_id', 'claim_status']);
                $table->index('ledger_transaction_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('egy_esg_marketplace_orders');
        Schema::dropIfExists('egy_esg_marketplace_listings');
        Schema::dropIfExists('egy_cbam_declarations');
        Schema::dropIfExists('egy_rec_certificates');
        Schema::dropIfExists('egy_carbon_trades');
        Schema::dropIfExists('egy_carbon_orders');
        Schema::dropIfExists('egy_carbon_credit_batches');
        Schema::dropIfExists('egy_carbon_projects');
    }
};
