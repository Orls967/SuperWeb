<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_ecosystem_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('partner_code')->unique();
            $table->string('partner_name');
            $table->string('tier_level'); // BRONZE, SILVER, GOLD, PLATINUM
            $table->decimal('ytd_revenue_usd', 15, 2)->default(0.00);
            $table->integer('downgrade_notice_days')->default(0); // 260.7
            $table->boolean('downgrade_pending')->default(false);
            $table->timestamps();
        });

        Schema::create('partner_cosell_deals', function (Blueprint $table) {
            $table->id();
            $table->string('deal_code')->unique();
            $table->string('registered_by_partner_code')->index();
            $table->decimal('deal_value_usd', 15, 2);
            $table->decimal('co_sell_rev_share_pct', 5, 2)->default(15.00);
            $table->decimal('settlement_amount_usd', 15, 2);
            $table->boolean('attribution_conflict_resolved')->default(false); // 260.6
            $table->string('attribution_priority_rule')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->string('referral_code')->unique();
            $table->string('affiliate_id')->index();
            $table->string('buyer_id');
            $table->decimal('sale_amount_usd', 15, 2);
            $table->decimal('commission_amount_usd', 15, 2);
            $table->boolean('is_fraud_detected')->default(false); // 260.5
            $table->string('payout_status')->default('PENDING'); // PENDING, PAID, HELD_FOR_INVESTIGATION
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_affiliate_referrals');
        Schema::dropIfExists('partner_cosell_deals');
        Schema::dropIfExists('partner_ecosystem_tiers');
    }
};
