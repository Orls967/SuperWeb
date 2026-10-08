<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_market_risk_positions', function (Blueprint $table) {
            $table->id();
            $table->string('desk_code')->unique();
            $table->string('asset_class'); // FX_USD_IDR, COMMODITY_NICKEL, RATES_SOFR
            $table->decimal('gross_exposure_usd', 18, 2);
            $table->decimal('var_99_1d_usd', 15, 2); // 309.1 Value at Risk 99% 1-day
            $table->decimal('var_risk_limit_usd', 15, 2);
            $table->boolean('is_var_breached')->default(false); // 309.1 & 309.4
            $table->boolean('emergency_hedge_authorized')->default(false); // 309.5 Edge case
            $table->timestamps();
        });

        Schema::create('treasury_counterparty_limits', function (Blueprint $table) {
            $table->id();
            $table->string('counterparty_code')->unique();
            $table->string('institution_name'); // BANK_MANDIRI, CITIBANK_NA, JP_MORGAN
            $table->decimal('current_credit_exposure_usd', 18, 2);
            $table->decimal('maximum_credit_limit_usd', 18, 2); // 309.3 Limit
            $table->boolean('limit_breach_detected')->default(false); // 309.3 & 309.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_counterparty_limits');
        Schema::dropIfExists('treasury_market_risk_positions');
    }
};
