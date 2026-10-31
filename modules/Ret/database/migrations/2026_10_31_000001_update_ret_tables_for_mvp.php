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
        if (! Schema::hasTable('ret_sellers')) {
            Schema::create('ret_sellers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('party_id');
                $table->string('seller_name');
                $table->string('kyb_status')->default('VERIFIED');
                $table->integer('commission_override_bps')->nullable();
                $table->string('payout_account_id')->nullable();
                $table->integer('map_violation_count')->default(0);
                $table->string('status')->default('ACTIVE');
                $table->timestamps();

                $table->index(['party_id', 'status']);
            });
        }

        if (! Schema::hasTable('ret_listings')) {
            Schema::create('ret_listings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('seller_id');
                $table->string('channel_id');
                $table->string('sku');
                $table->string('title');
                $table->bigInteger('price_minor');
                $table->bigInteger('map_price_minor');
                $table->boolean('is_active')->default(true);
                $table->string('idempotency_key')->nullable()->unique();
                $table->timestamps();

                $table->index(['seller_id', 'sku']);
                $table->index(['channel_id', 'sku']);
            });
        }

        if (! Schema::hasTable('ret_order_items')) {
            Schema::create('ret_order_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('order_id');
                $table->string('sku');
                $table->integer('quantity');
                $table->bigInteger('unit_price_minor');
                $table->bigInteger('subtotal_minor');
                $table->timestamps();

                $table->index(['order_id', 'sku']);
            });
        }

        Schema::table('ret_channels', function (Blueprint $table) {
            if (! Schema::hasColumn('ret_channels', 'default_commission_bps')) {
                $table->integer('default_commission_bps')->default(500);
            }
        });

        Schema::table('ret_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('ret_orders', 'seller_id')) {
                $table->string('seller_id')->nullable();
            }
            if (! Schema::hasColumn('ret_orders', 'commission_amount_minor')) {
                $table->bigInteger('commission_amount_minor')->default(0);
            }
            if (! Schema::hasColumn('ret_orders', 'packaging_deposit_minor')) {
                $table->bigInteger('packaging_deposit_minor')->default(0);
            }
            if (! Schema::hasColumn('ret_orders', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique();
            }
            if (! Schema::hasColumn('ret_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (! Schema::hasColumn('ret_orders', 'cancellation_reason')) {
                $table->string('cancellation_reason')->nullable();
            }
        });

        Schema::table('ret_seller_settlements', function (Blueprint $table) {
            if (! Schema::hasColumn('ret_seller_settlements', 'order_id')) {
                $table->string('order_id')->nullable();
            }
            if (! Schema::hasColumn('ret_seller_settlements', 'seller_id')) {
                $table->string('seller_id')->nullable();
            }
            if (! Schema::hasColumn('ret_seller_settlements', 'gross_order_amount_minor')) {
                $table->bigInteger('gross_order_amount_minor')->default(0);
            }
            if (! Schema::hasColumn('ret_seller_settlements', 'commission_amount_minor')) {
                $table->bigInteger('commission_amount_minor')->default(0);
            }
            if (! Schema::hasColumn('ret_seller_settlements', 'net_seller_payable_minor')) {
                $table->bigInteger('net_seller_payable_minor')->default(0);
            }
            if (! Schema::hasColumn('ret_seller_settlements', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique();
            }
        });

        Schema::table('ret_cashback_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('ret_cashback_transactions', 'amount_minor')) {
                $table->bigInteger('amount_minor')->default(0);
            }
            if (! Schema::hasColumn('ret_cashback_transactions', 'type')) {
                $table->string('type')->default('EARNED');
            }
            if (! Schema::hasColumn('ret_cashback_transactions', 'order_id')) {
                $table->string('order_id')->nullable();
            }
            if (! Schema::hasColumn('ret_cashback_transactions', 'reference_id')) {
                $table->string('reference_id')->nullable();
            }
            if (! Schema::hasColumn('ret_cashback_transactions', 'source_domain')) {
                $table->string('source_domain')->nullable();
            }
            if (! Schema::hasColumn('ret_cashback_transactions', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }
            if (! Schema::hasColumn('ret_cashback_transactions', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique();
            }
        });

        Schema::table('ret_packaging_deposits', function (Blueprint $table) {
            if (! Schema::hasColumn('ret_packaging_deposits', 'order_id')) {
                $table->string('order_id')->nullable();
            }
            if (! Schema::hasColumn('ret_packaging_deposits', 'package_code')) {
                $table->string('package_code')->nullable();
            }
            if (! Schema::hasColumn('ret_packaging_deposits', 'return_station_id')) {
                $table->string('return_station_id')->nullable();
            }
            if (! Schema::hasColumn('ret_packaging_deposits', 'inspected_condition')) {
                $table->string('inspected_condition')->nullable();
            }
            if (! Schema::hasColumn('ret_packaging_deposits', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable();
            }
            if (! Schema::hasColumn('ret_packaging_deposits', 'forfeited_at')) {
                $table->timestamp('forfeited_at')->nullable();
            }
            if (! Schema::hasColumn('ret_packaging_deposits', 'forfeit_reason')) {
                $table->string('forfeit_reason')->nullable();
            }
            if (! Schema::hasColumn('ret_packaging_deposits', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique();
            }
        });

        Schema::table('ret_quick_commerce_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('ret_quick_commerce_orders', 'order_id')) {
                $table->string('order_id')->nullable();
            }
            if (! Schema::hasColumn('ret_quick_commerce_orders', 'driver_type')) {
                $table->string('driver_type')->default('INTERNAL');
            }
            if (! Schema::hasColumn('ret_quick_commerce_orders', 'driver_user_id')) {
                $table->string('driver_user_id')->nullable();
            }
            if (! Schema::hasColumn('ret_quick_commerce_orders', 'pod_signature_hash')) {
                $table->string('pod_signature_hash')->nullable();
            }
            if (! Schema::hasColumn('ret_quick_commerce_orders', 'compensation_credit_minor')) {
                $table->bigInteger('compensation_credit_minor')->default(0);
            }
            if (! Schema::hasColumn('ret_quick_commerce_orders', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique();
            }
        });

        Schema::table('ret_fulfillment_splits', function (Blueprint $table) {
            if (! Schema::hasColumn('ret_fulfillment_splits', 'source_location_id')) {
                $table->string('source_location_id')->nullable();
            }
            if (! Schema::hasColumn('ret_fulfillment_splits', 'source_type')) {
                $table->string('source_type')->default('DARK_STORE');
            }
            if (! Schema::hasColumn('ret_fulfillment_splits', 'allocated_quantity')) {
                $table->integer('allocated_quantity')->default(0);
            }
        });

        Schema::table('ret_coupon_redemptions', function (Blueprint $table) {
            if (! Schema::hasColumn('ret_coupon_redemptions', 'channel_id')) {
                $table->string('channel_id')->nullable();
            }
            if (! Schema::hasColumn('ret_coupon_redemptions', 'discount_minor')) {
                $table->bigInteger('discount_minor')->default(0);
            }
            if (! Schema::hasColumn('ret_coupon_redemptions', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ret_order_items');
        Schema::dropIfExists('ret_listings');
        Schema::dropIfExists('ret_sellers');
    }
};
