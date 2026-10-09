<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_securities_issuances', function (Blueprint $table) {
            $table->id();
            $table->string('issuance_code')->unique();
            $table->string('token_symbol');
            $table->string('security_type'); // EQUITY_TOKEN, DEBT_BOND_TOKEN
            $table->decimal('total_tokens_issued', 18, 4);
            $table->decimal('token_face_value_usd', 15, 2);
            $table->decimal('total_allocated_tokens', 18, 4)->default(0.0000); // 271.4 issuance sum check
            $table->string('status')->default('BOOKBUILDING'); // BOOKBUILDING, ALLOCATED, TRADING, MATURED
            $table->timestamps();
        });

        Schema::create('digital_securities_investors', function (Blueprint $table) {
            $table->id();
            $table->string('investor_id')->unique();
            $table->string('kyc_tier'); // TIER_1, TIER_2, INSTITUTIONAL
            $table->string('suitability_grade'); // CONSERVATIVE, BALANCED, AGGRESSIVE
            $table->boolean('is_access_suspended')->default(false); // 271.6
            $table->timestamps();
        });

        Schema::create('digital_securities_market_making', function (Blueprint $table) {
            $table->id();
            $table->string('market_symbol')->unique();
            $table->decimal('bid_price_usd', 15, 4);
            $table->decimal('ask_price_usd', 15, 4);
            $table->decimal('spread_pct', 5, 2);
            $table->decimal('current_inventory_usd', 15, 2);
            $table->decimal('max_inventory_limit_usd', 15, 2); // 271.3, 271.4
            $table->decimal('orderbook_depth_usd', 15, 2);
            $table->boolean('is_liquidity_thin_warning')->default(false); // 271.5
            $table->timestamps();
        });

        Schema::create('digital_securities_corporate_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_code')->unique();
            $table->string('issuance_code')->index();
            $table->string('action_type'); // DIVIDEND_PAYOUT, COUPON_PAYMENT, REDEMPTION
            $table->integer('notice_period_days'); // 271.7 notice period
            $table->date('execution_date');
            $table->boolean('is_executed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_securities_corporate_actions');
        Schema::dropIfExists('digital_securities_market_making');
        Schema::dropIfExists('digital_securities_investors');
        Schema::dropIfExists('digital_securities_issuances');
    }
};
