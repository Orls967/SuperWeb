<?php

declare(strict_types=1);

namespace Modules\Ret\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 137.1 Channels (Physical Store, Web App, 3P Marketplace)
        Schema::create('ret_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel_code')->unique();
            $table->string('channel_name');
            $table->string('channel_type'); // PHYSICAL_STORE, WEB_APP, THIRD_PARTY_MARKETPLACE
            $table->double('default_commission_pct', 5, 2)->default(5.0);
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        // 137.1 & 137.3 Unified Inventory & Listings
        Schema::create('ret_inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku')->unique();
            $table->string('product_name');
            $table->integer('stock_available')->default(0);
            $table->integer('stock_reserved')->default(0);
            $table->bigInteger('map_price_minor'); // Minimum Advertised Price
            $table->timestamps();
        });

        // 137.2 Multi-Vendor Marketplace 3P Sellers & Settlements
        Schema::create('ret_seller_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('settlement_code')->unique();
            $table->string('seller_party_id');
            $table->bigInteger('verified_gmv_minor');
            $table->double('commission_rate_pct', 5, 2);
            $table->bigInteger('commission_fee_minor');
            $table->bigInteger('net_payout_minor');
            $table->string('status')->default('SETTLED');
            $table->timestamps();

            $table->index(['seller_party_id', 'status']);
        });

        // 137.3 & 137.4 Orders & Fulfillment Split
        Schema::create('ret_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();
            $table->string('customer_id');
            $table->string('channel_id');
            $table->bigInteger('total_amount_minor');
            $table->string('coupon_code')->nullable();
            $table->string('status')->default('PAID'); // PAID, SPLIT, FULFILLED, CANCELLED
            $table->timestamps();
        });

        Schema::create('ret_fulfillment_splits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('split_code')->unique();
            $table->string('order_id');
            $table->string('sku');
            $table->integer('quantity');
            $table->string('fulfillment_location_type'); // SHIP_AS_STORE, FDC_WAREHOUSE, DROPSHIP
            $table->string('facility_id');
            $table->string('status')->default('PENDING_PICK');
            $table->timestamps();

            $table->index(['order_id', 'sku']);
        });

        // 137.5 Coupons & Promotions anti-double-use
        Schema::create('ret_coupon_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('coupon_code');
            $table->string('customer_id');
            $table->string('order_id');
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->unique(['coupon_code', 'customer_id']); // Enforce anti-double-use per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ret_coupon_redemptions');
        Schema::dropIfExists('ret_fulfillment_splits');
        Schema::dropIfExists('ret_orders');
        Schema::dropIfExists('ret_seller_settlements');
        Schema::dropIfExists('ret_inventory_items');
        Schema::dropIfExists('ret_channels');
    }
};
