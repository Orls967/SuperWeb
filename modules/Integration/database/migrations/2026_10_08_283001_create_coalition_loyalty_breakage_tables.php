<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coalition_loyalty_partners', function (Blueprint $table) {
            $table->id();
            $table->string('partner_code')->unique();
            $table->string('industry_sector'); // AIRLINE, HOTEL, RETAIL, INSURANCE, TELCO
            $table->decimal('interchange_fee_rate_pct', 5, 2);
            $table->decimal('outstanding_settlement_usd', 15, 2)->default(0.00);
            $table->decimal('default_reserve_usd', 15, 2)->default(0.00); // 283.6
            $table->boolean('is_in_default_escalation')->default(false); // 283.6
            $table->timestamps();
        });

        Schema::create('loyalty_liability_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('account_id')->index();
            $table->decimal('points_balance', 18, 4);
            $table->decimal('point_valuation_usd', 8, 4)->default(0.0100);
            $table->decimal('total_liability_usd', 15, 2); // 283.1 & 283.4
            $table->boolean('is_grandfathered_rate')->default(false); // 283.5
            $table->timestamps();
        });

        Schema::create('loyalty_breakage_recognitions', function (Blueprint $table) {
            $table->id();
            $table->string('recognition_code')->unique();
            $table->string('period_name'); // e.g. 2026-Q3
            $table->decimal('forecast_redemption_rate_pct', 5, 2);
            $table->decimal('actual_redemption_rate_pct', 5, 2)->nullable();
            $table->decimal('breakage_revenue_recognized_usd', 15, 2);
            $table->decimal('reversal_adjustment_usd', 15, 2)->default(0.00); // 283.2 & 283.7
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_breakage_recognitions');
        Schema::dropIfExists('loyalty_liability_ledgers');
        Schema::dropIfExists('coalition_loyalty_partners');
    }
};
