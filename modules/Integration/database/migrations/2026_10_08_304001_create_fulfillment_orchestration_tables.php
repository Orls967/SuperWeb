<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_order_promises', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->string('customer_channel'); // WEB, STORE, MARKETPLACE, B2B
            $table->string('sku');
            $table->integer('committed_quantity');
            $table->date('promised_delivery_date');
            $table->decimal('frozen_contract_price_usd', 15, 2); // 304.6 Price freeze guard
            $table->boolean('is_stock_lost_post_promise')->default(false); // 304.5 Edge case
            $table->decimal('automatic_compensation_usd', 15, 2)->default(0.00); // 304.3 & 304.5
            $table->timestamps();
        });

        Schema::create('fulfillment_orchestration_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code')->unique();
            $table->string('source_fulfillment_node'); // REGIONAL_DC, LOCAL_STORE, DROPSHIP
            $table->boolean('price_freeze_contract_honored')->default(true); // 304.6 Contract guard
            $table->decimal('cost_to_serve_usd', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_orchestration_rules');
        Schema::dropIfExists('fulfillment_order_promises');
    }
};
