<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kam_key_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique();
            $table->string('client_name');
            $table->string('account_tier'); // STRATEGIC_ENTERPRISE, TIER_1_GOV, GLOBAL_CONGLOMERATE
            $table->string('lead_account_director');
            $table->timestamps();
        });

        Schema::create('kam_multi_line_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_code')->unique();
            $table->unsignedBigInteger('account_id');
            $table->string('bundle_name');
            $table->decimal('total_contract_price_usd', 15, 2);
            $table->decimal('internal_margin_usd', 15, 2);
            $table->string('status')->default('ACTIVE'); // ACTIVE, RECALCULATED, TERMINATED
            $table->date('contract_end_date');
            $table->integer('renewal_alert_days')->default(0); // 247.7
            $table->timestamps();
        });

        Schema::create('kam_bundle_line_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bundle_id');
            $table->string('business_line');
            $table->decimal('allocated_price_usd', 15, 2);
            $table->decimal('allocated_cogs_usd', 15, 2);
            $table->decimal('allocated_margin_usd', 15, 2);
            $table->boolean('is_cancelled')->default(false); // 247.6
            $table->timestamps();
        });

        Schema::create('kam_partnership_referral_shares', function (Blueprint $table) {
            $table->id();
            $table->string('referral_code')->unique();
            $table->string('partner_id')->index();
            $table->decimal('deal_value_usd', 15, 2);
            $table->decimal('share_pct', 5, 2);
            $table->decimal('calculated_payout_usd', 15, 2);
            $table->string('payout_status')->default('PENDING'); // PENDING, PAID, DISPUTED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kam_partnership_referral_shares');
        Schema::dropIfExists('kam_bundle_line_components');
        Schema::dropIfExists('kam_multi_line_bundles');
        Schema::dropIfExists('kam_key_accounts');
    }
};
