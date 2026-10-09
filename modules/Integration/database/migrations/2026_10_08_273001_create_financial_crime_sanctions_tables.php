<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fincrime_sanction_entities', function (Blueprint $table) {
            $table->id();
            $table->string('entity_code')->unique();
            $table->string('entity_name');
            $table->string('parent_entity_code')->nullable(); // UBO chain
            $table->decimal('ubo_ownership_pct', 5, 2)->default(0.00); // 273.1, 273.4
            $table->boolean('is_sanctioned_directly')->default(false);
            $table->boolean('is_sanctioned_via_ubo')->default(false); // 273.1
            $table->decimal('hit_confidence_score', 4, 3)->default(0.000);
            $table->boolean('operational_blocked')->default(false);
            $table->boolean('is_appealed')->default(false); // 273.5
            $table->string('appeal_status')->default('NONE'); // NONE, UNDER_REVIEW, OVERTURNED_UNBLOCKED
            $table->timestamps();
        });

        Schema::create('fincrime_trade_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('trade_ref')->unique();
            $table->string('goods_commodity_code');
            $table->decimal('unit_price_usd', 15, 2);
            $table->decimal('benchmark_index_price_usd', 15, 2);
            $table->decimal('price_deviation_pct', 5, 2);
            $table->boolean('is_dual_use_goods')->default(false); // 273.2
            $table->boolean('is_circular_trade')->default(false); // 273.2
            $table->string('risk_assessment')->default('LOW_RISK'); // LOW_RISK, SUSPICIOUS_AML_FLAGGED
            $table->timestamps();
        });

        Schema::create('fincrime_crypto_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_address')->unique();
            $table->string('cluster_category'); // EXCHANGE, MIXER_TORNADO, ILLICIT_DARKNET, UNKNOWN
            $table->decimal('exposure_risk_score', 4, 2); // 0 to 10
            $table->boolean('is_hold_blocked')->default(false); // 273.3 & 273.4
            $table->timestamps();
        });

        Schema::create('fincrime_structuring_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_code')->unique();
            $table->string('party_id')->index();
            $table->integer('split_transaction_count');
            $table->decimal('cumulative_amount_usd', 15, 2);
            $table->boolean('is_structuring_flagged')->default(false); // 273.7
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fincrime_structuring_alerts');
        Schema::dropIfExists('fincrime_crypto_wallets');
        Schema::dropIfExists('fincrime_trade_transactions');
        Schema::dropIfExists('fincrime_sanction_entities');
    }
};
