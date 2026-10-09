<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_rolling_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('forecast_code')->unique();
            $table->string('entity_code')->index();
            $table->integer('horizon_weeks')->default(13); // 275.1
            $table->decimal('predicted_inflow_usd', 15, 2);
            $table->decimal('predicted_outflow_usd', 15, 2);
            $table->decimal('actual_inflow_usd', 15, 2)->nullable();
            $table->decimal('actual_outflow_usd', 15, 2)->nullable();
            $table->decimal('accuracy_pct', 5, 2)->default(100.00);
            $table->boolean('bias_correction_required')->default(false); // 275.1, 275.5
            $table->timestamps();
        });

        Schema::create('cash_intraday_positions', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique();
            $table->string('bank_name');
            $table->decimal('current_balance_usd', 15, 2);
            $table->decimal('projected_eod_balance_usd', 15, 2);
            $table->timestamp('last_synced_at'); // 275.6 freshness SLA
            $table->boolean('is_stale')->default(false);
            $table->timestamps();
        });

        Schema::create('cash_liquidity_stress_tests', function (Blueprint $table) {
            $table->id();
            $table->string('test_code')->unique();
            $table->string('scenario_name'); // MAJOR_CUSTOMER_LOSS, MARKET_FREEZE, NATURAL_DISASTER
            $table->decimal('available_liquidity_usd', 15, 2);
            $table->decimal('daily_burn_rate_usd', 15, 2);
            $table->integer('survival_days'); // 275.3 & 275.4
            $table->boolean('board_alert_triggered')->default(false); // 275.3
            $table->boolean('preapproved_contingency_active')->default(true); // 275.7
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_liquidity_stress_tests');
        Schema::dropIfExists('cash_intraday_positions');
        Schema::dropIfExists('cash_rolling_forecasts');
    }
};
