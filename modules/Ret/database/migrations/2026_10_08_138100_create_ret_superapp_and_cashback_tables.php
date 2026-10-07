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
        // 138.2 Cross-lini Cashback Economy
        Schema::create('ret_cashback_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cashback_code')->unique();
            $table->string('customer_id');
            $table->string('source_line'); // RESTO, VENUE, HOTEL, EV_CHARGING, UTILITY
            $table->bigInteger('transaction_amount_minor');
            $table->double('cashback_pct', 5, 2);
            $table->bigInteger('cashback_earned_minor');
            $table->string('status')->default('EARNED'); // EARNED, REDEEMED, VOIDED
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        // 138.3 Bill Payment Hub with Gapless Receipts
        Schema::create('ret_bill_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receipt_number')->unique(); // Sequential gapless receipt
            $table->bigInteger('receipt_sequence');
            $table->string('customer_id');
            $table->string('bill_type'); // UTILITY_WATER, ELECTRICITY_PLN, BPJS_HEALTH, TENANT_LEASE
            $table->string('biller_code');
            $table->bigInteger('bill_amount_minor');
            $table->bigInteger('admin_fee_minor')->default(250000); // 2,500 IDR
            $table->bigInteger('total_paid_minor');
            $table->string('status')->default('PAID');
            $table->timestamps();

            $table->index(['customer_id', 'receipt_sequence']);
        });

        // 138.4 Cross-Lini Subscription Bundles
        Schema::create('ret_subscription_bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bundle_code')->unique();
            $table->string('bundle_name');
            $table->bigInteger('monthly_price_minor');
            $table->json('line_settlement_breakdown'); // e.g. {"HOTEL": 40000000, "MEDIA": 10000000, "TLX": 25000000, "EV": 25000000}
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('ret_bundle_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subscription_code')->unique();
            $table->string('bundle_id');
            $table->string('customer_id');
            $table->bigInteger('amount_billed_minor');
            $table->string('billing_period'); // YYYY-MM
            $table->string('status')->default('SETTLED');
            $table->timestamps();

            $table->index(['customer_id', 'billing_period']);
        });

        // 138.5 Behavioral offer & privacy opt-out
        Schema::create('ret_customer_privacy_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('customer_id')->unique();
            $table->boolean('marketing_analytics_opt_out')->default(false);
            $table->string('assigned_segment')->default('GENERAL');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ret_customer_privacy_profiles');
        Schema::dropIfExists('ret_bundle_subscriptions');
        Schema::dropIfExists('ret_subscription_bundles');
        Schema::dropIfExists('ret_bill_payments');
        Schema::dropIfExists('ret_cashback_transactions');
    }
};
