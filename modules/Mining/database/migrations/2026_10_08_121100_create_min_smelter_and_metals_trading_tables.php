<?php

declare(strict_types=1);

namespace Modules\Mining\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('min_smelter_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('site_id');
            $table->string('run_code')->unique();
            $table->string('output_commodity'); // NPI, MATTE, COPPER_CATHODE
            $table->double('input_ore_tonnage', 12, 3);
            $table->double('input_grade_pct', 5, 2);
            $table->double('output_metal_tonnage', 12, 3);
            $table->double('recovery_rate_pct', 5, 2);
            $table->double('byproduct_slag_tonnage', 12, 3)->default(0.0);
            $table->bigInteger('byproduct_revenue_idr')->default(0);
            $table->double('energy_kwh_per_ton', 10, 2)->default(0.0);
            $table->string('status')->default('COMPLETED');
            $table->timestamps();

            $table->index(['site_id', 'output_commodity']);
        });

        Schema::create('min_offtaker_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_number')->unique();
            $table->string('buyer_party_id');
            $table->string('commodity'); // NICKEL, COPPER, COBALT
            $table->double('contracted_tonnage', 12, 3);
            $table->double('base_lme_price_usd_per_ton', 12, 2);
            $table->double('premium_discount_usd', 12, 2)->default(0.0);
            $table->bigInteger('invoiced_amount_minor')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('settlement_status')->default('PENDING'); // PENDING, INVOICED, SETTLED
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['buyer_party_id', 'commodity']);
        });

        Schema::create('min_metals_trading_positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('desk_code');
            $table->string('commodity');
            $table->string('position_type'); // LONG, SHORT
            $table->double('tonnage', 12, 3);
            $table->double('entry_price_usd', 12, 2);
            $table->double('current_mark_price_usd', 12, 2);
            $table->bigInteger('unrealized_pnl_minor')->default(0);
            $table->string('status')->default('OPEN'); // OPEN, CLOSED
            $table->timestamps();

            $table->index(['desk_code', 'commodity']);
        });

        Schema::create('min_metal_warehouse_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receipt_number')->unique();
            $table->string('warehouse_id');
            $table->string('commodity');
            $table->double('stored_tonnage', 12, 3);
            $table->string('receipt_hash')->unique();
            $table->boolean('is_collateralized')->default(false);
            $table->string('collateral_facility_id')->nullable();
            $table->bigInteger('financing_amount_minor')->default(0);
            $table->string('status')->default('ACTIVE'); // ACTIVE, PLEDGED, RELEASED
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_metal_warehouse_receipts');
        Schema::dropIfExists('min_metals_trading_positions');
        Schema::dropIfExists('min_offtaker_contracts');
        Schema::dropIfExists('min_smelter_runs');
    }
};
