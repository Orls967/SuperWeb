<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_wealth_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('ticker_symbol', 32)->unique();
            $table->string('name', 191);
            $table->string('instrument_type', 32)->default('SHARIAH_EQUITY');
            $table->unsignedBigInteger('interest_bearing_debt_idr')->default(0);
            $table->unsignedBigInteger('total_assets_idr')->default(1);
            $table->unsignedSmallInteger('debt_ratio_bps')->default(0);
            $table->unsignedBigInteger('non_halal_revenue_idr')->default(0);
            $table->unsignedBigInteger('total_revenue_idr')->default(1);
            $table->unsignedSmallInteger('non_halal_revenue_ratio_bps')->default(0);
            $table->string('sector_code', 64)->default('GENERAL_COMMERCE');
            $table->boolean('is_shariah_compliant')->default(false);
            $table->timestamp('last_screened_at')->nullable();
            $table->timestamps();

            $table->index(['is_shariah_compliant', 'instrument_type'], 'idx_syb_inst_comp_type');
        });

        Schema::create('syb_wealth_portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('risk_profile', 32)->default('MODERATE');
            $table->unsignedBigInteger('current_balance_idr')->default(0);
            $table->unsignedBigInteger('total_invested_idr')->default(0);
            $table->unsignedBigInteger('total_purification_idr')->default(0);
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_syb_port_user_status');
        });

        Schema::create('syb_wealth_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained('syb_wealth_portfolios')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('syb_wealth_instruments');
            $table->string('order_type', 16)->default('BUY');
            $table->unsignedInteger('units');
            $table->unsignedBigInteger('amount_idr');
            $table->string('status', 16)->default('PENDING');
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_wealth_orders');
        Schema::dropIfExists('syb_wealth_portfolios');
        Schema::dropIfExists('syb_wealth_instruments');
    }
};
