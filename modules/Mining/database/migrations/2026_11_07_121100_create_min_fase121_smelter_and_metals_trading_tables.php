<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tanur Peleburan Smelter & Kiln RKEF (121.1)
        Schema::create('min_smelter_furnaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->nullable()->constrained('min_sites')->cascadeOnDelete();
            $table->string('furnace_code', 32)->unique();
            $table->string('name', 128);
            $table->string('furnace_type', 64)->default('RKEF_ELECTRIC_ARC'); // RKEF_ELECTRIC_ARC, ROTARY_KILN, HPAL_AUTOCLAVE
            $table->decimal('rated_capacity_tons_day', 10, 2)->default(500.0);
            $table->decimal('transformer_power_mva', 8, 2)->default(45.0);
            $table->decimal('operating_temperature_celsius', 8, 2)->default(1550.0);
            $table->decimal('electrode_diameter_mm', 8, 2)->default(1200.0);
            $table->string('target_metal', 32)->default('NICKEL_PIG_IRON'); // NICKEL_PIG_IRON, FERRONICKEL, NICKEL_MATTE, COPPER_CATHODE
            $table->string('status', 32)->default('OPERATIONAL'); // OPERATIONAL, MAINTENANCE, STANDBY, RELINING
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 2. Siklus Batch Peleburan Tanur Smelter (121.1)
        Schema::create('min_smelter_furnace_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('furnace_id')->constrained('min_smelter_furnaces')->cascadeOnDelete();
            $table->string('run_code', 64)->unique();
            $table->string('batch_lot_number', 64)->nullable();
            $table->decimal('ore_feed_wmt', 10, 2)->default(0.0);
            $table->decimal('ore_moisture_pct', 5, 2)->default(30.0);
            $table->decimal('ore_feed_dmt', 10, 2)->default(0.0);
            $table->decimal('ore_ni_grade_pct', 5, 2)->default(1.80);
            $table->decimal('ore_fe_grade_pct', 5, 2)->default(18.50);
            $table->decimal('ore_sio2_mgo_ratio', 6, 3)->default(1.95);
            $table->decimal('coal_reductant_tons', 10, 2)->default(0.0);
            $table->decimal('silica_flux_tons', 10, 2)->default(0.0);
            $table->decimal('power_consumption_mwh', 10, 3)->default(0.0);
            $table->decimal('tapping_temperature_c', 8, 2)->default(1520.0);
            $table->decimal('metal_output_tons', 10, 2)->default(0.0);
            $table->decimal('metal_recovery_rate_pct', 5, 2)->default(92.50);
            $table->decimal('slag_output_tons', 10, 2)->default(0.0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('tapped_at')->nullable();
            $table->string('operator_in_charge', 64)->default('Smelter Chief Metallurgist');
            $table->string('hmac_signature', 64)->nullable();
            $table->string('status', 32)->default('DRAFT');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 3. Pencatatan Energi Spesifik & Emisi GRK Scope 1/2 (121.1)
        Schema::create('min_smelter_energy_emissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('furnace_run_id')->constrained('min_smelter_furnace_runs')->cascadeOnDelete();
            $table->string('emission_code', 64)->unique();
            $table->decimal('electricity_mwh', 10, 3)->default(0.0);
            $table->decimal('electricity_emission_factor_tco2_mwh', 6, 4)->default(0.7900);
            $table->decimal('coal_burned_tons', 10, 2)->default(0.0);
            $table->decimal('coal_emission_factor_tco2_ton', 6, 4)->default(2.4200);
            $table->decimal('diesel_fuel_liters', 10, 2)->default(0.0);
            $table->decimal('scope1_emissions_tco2e', 10, 3)->default(0.0);
            $table->decimal('scope2_emissions_tco2e', 10, 3)->default(0.0);
            $table->decimal('total_emissions_tco2e', 10, 3)->default(0.0);
            $table->decimal('intensity_tco2e_per_ton_metal', 8, 4)->default(0.0);
            $table->boolean('carbon_tax_threshold_exceeded')->default(false);
            $table->string('status', 32)->default('VERIFIED');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 4. Pengujian Kadar Mutu Laboratorium Spektrometri / XRF (121.2)
        Schema::create('min_smelter_quality_assays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('furnace_run_id')->nullable()->constrained('min_smelter_furnace_runs')->nullOnDelete();
            $table->string('assay_number', 64)->unique();
            $table->string('sample_code', 64)->nullable();
            $table->string('metal_commodity', 32)->default('NPI'); // NPI, FENI, CU_CATHODE, CO_BRIQUETTE
            $table->decimal('primary_metal_grade_pct', 6, 3)->default(12.50); // e.g. 12.5% Ni di NPI
            $table->decimal('secondary_metal_grade_pct', 6, 3)->default(0.0);
            $table->decimal('iron_fe_pct', 6, 3)->default(80.0);
            $table->decimal('carbon_c_pct', 6, 3)->default(2.10);
            $table->decimal('silicon_si_pct', 6, 3)->default(1.80);
            $table->decimal('phosphorus_p_pct', 6, 4)->default(0.025);
            $table->decimal('sulfur_s_pct', 6, 4)->default(0.035);
            $table->decimal('moisture_pct', 5, 2)->default(0.10);
            $table->boolean('meets_lme_specification')->default(true);
            $table->string('chief_chemist', 64)->default('Lab Chief Chemist');
            $table->timestamp('analyzed_at')->nullable();
            $table->string('hmac_signature', 64)->nullable();
            $table->string('status', 32)->default('CERTIFIED');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 5. Kontrak Penjualan & Lindung Nilai Bursa LME (121.2, 121.3)
        Schema::create('min_metals_trading_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_number', 64)->unique();
            $table->string('counterparty_name', 128);
            $table->string('metal_symbol', 16)->default('LME-NI'); // LME-NI, LME-CU, LME-CO
            $table->string('contract_type', 32)->default('PHYSICAL_OFFTAKE'); // PHYSICAL_OFFTAKE, HEDGE_FORWARD, HEDGE_SWAP
            $table->decimal('contract_quantity_metric_tons', 10, 2)->default(1000.0);
            $table->integer('fixed_price_usd_cents_per_ton')->default(1650000); // e.g. $16,500/ton = 1,650,000 cents
            $table->string('pricing_basis', 32)->default('LME_CASH_MONTHLY_AVG'); // LME_CASH_MONTHLY_AVG, FIXED, LME_3M_SPREAD
            $table->integer('premium_discount_usd_cents_ton')->default(0);
            $table->integer('initial_margin_idr')->default(0);
            $table->integer('maintenance_margin_idr')->default(0);
            $table->date('delivery_period_start')->nullable();
            $table->date('delivery_period_end')->nullable();
            $table->string('trader_name', 64)->default('Metals Desk Chief Trader');
            $table->string('status', 32)->default('DRAFT');
            $table->string('hmac_signature', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 6. Posisi Harian Mark-to-Market & Margin Call Trading Desk (121.3)
        Schema::create('min_metals_hedge_positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_id')->constrained('min_metals_trading_contracts')->cascadeOnDelete();
            $table->string('position_code', 64)->unique();
            $table->date('valuation_date');
            $table->string('position_type', 16)->default('LONG'); // LONG, SHORT
            $table->decimal('open_lots', 10, 2)->default(10.0);
            $table->decimal('metric_tons', 10, 2)->default(250.0);
            $table->integer('entry_price_usd_cents_per_ton')->default(1650000);
            $table->integer('current_lme_settlement_cents_per_ton')->default(1700000);
            $table->integer('unrealized_pnl_usd_cents')->default(0);
            $table->bigInteger('unrealized_pnl_idr')->default(0);
            $table->integer('variation_margin_required_idr')->default(0);
            $table->boolean('margin_call_triggered')->default(false);
            $table->string('status', 32)->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 7. Sistem Resi Gudang (SRG) Digital Beragun Pembiayaan SCF (121.4)
        Schema::create('min_metals_warehouse_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assay_id')->nullable()->constrained('min_smelter_quality_assays')->nullOnDelete();
            $table->string('receipt_number', 64)->unique();
            $table->string('warehouse_code', 32)->default('WMS-BAPPEBTI-01');
            $table->string('warehouse_operator_name', 128)->default('PT Kliring Berjangka Indonesia Logistik');
            $table->string('metal_commodity', 32)->default('NPI');
            $table->decimal('certified_weight_metric_tons', 10, 3)->default(100.0);
            $table->integer('bundle_or_ingot_count')->default(2500);
            $table->string('storage_location_rack', 64)->default('ZONE-VALUABLE-RACK-04');
            $table->integer('estimated_market_value_idr')->default(0);
            $table->decimal('haircut_percentage', 5, 2)->default(20.00); // 20% haircut -> LTV 80%
            $table->integer('max_financing_limit_idr')->default(0);
            $table->string('pledged_bank_name', 128)->nullable();
            $table->string('scf_facility_reference', 64)->nullable();
            $table->date('issued_at');
            $table->date('expires_at');
            $table->string('digital_seal_hash', 64)->nullable();
            $table->string('hmac_signature', 64)->nullable();
            $table->string('status', 32)->default('ISSUED');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 8. Produk Sampingan Smelter & Ekonomi Sirkular (121.5)
        Schema::create('min_smelter_byproducts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('furnace_run_id')->nullable()->constrained('min_smelter_furnace_runs')->nullOnDelete();
            $table->string('byproduct_code', 64)->unique();
            $table->string('byproduct_type', 64)->default('NICKEL_SLAG'); // NICKEL_SLAG, SULFURIC_ACID, GYPSUM, FLUE_DUST
            $table->decimal('generated_quantity_tons', 10, 2)->default(0.0);
            $table->decimal('dispatched_quantity_tons', 10, 2)->default(0.0);
            $table->decimal('stock_remaining_tons', 10, 2)->default(0.0);
            $table->integer('unit_price_idr_per_ton')->default(75000); // e.g. Rp 75.000 / ton slag
            $table->integer('total_revenue_idr')->default(0);
            $table->string('buyer_party_code', 64)->nullable();
            $table->string('utilization_application', 128)->default('Road Base & Ready-Mix Concrete Aggregate');
            $table->string('environmental_manifest_number', 64)->nullable();
            $table->string('status', 32)->default('STORED'); // STORED, PARTIALLY_DISPATCHED, SOLD, RECYCLED
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_smelter_byproducts');
        Schema::dropIfExists('min_metals_warehouse_receipts');
        Schema::dropIfExists('min_metals_hedge_positions');
        Schema::dropIfExists('min_metals_trading_contracts');
        Schema::dropIfExists('min_smelter_quality_assays');
        Schema::dropIfExists('min_smelter_energy_emissions');
        Schema::dropIfExists('min_smelter_furnace_runs');
        Schema::dropIfExists('min_smelter_furnaces');
    }
};
