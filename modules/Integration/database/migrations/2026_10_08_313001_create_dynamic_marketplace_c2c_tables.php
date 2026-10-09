<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('c2c_marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->string('listing_code')->unique();
            $table->string('seller_id')->index();
            $table->string('category'); // USED_VEHICLE, LUXURY_FASHION, ELECTRONICS
            $table->decimal('asking_price_usd', 15, 2);
            $table->boolean('identity_verified')->default(false); // 313.5 Identity verification requirement
            $table->integer('active_listing_velocity_count')->default(1); // 313.5 Velocity limit
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('c2c_marketplace_escrows', function (Blueprint $table) {
            $table->id();
            $table->string('escrow_code')->unique();
            $table->string('listing_code')->index();
            $table->decimal('escrow_amount_usd', 15, 2);
            $table->string('buyer_id');
            $table->string('seller_id');
            $table->boolean('is_disputed')->default(false); // 313.3 & 313.6
            $table->boolean('is_dispute_resolved')->default(false);
            $table->boolean('funds_released')->default(false); // 313.4 Escrow funds held until delivery/resolution
            $table->timestamps();
        });

        Schema::create('c2b_buyback_offers', function (Blueprint $table) {
            $table->id();
            $table->string('offer_code')->unique();
            $table->string('asset_sku')->index();
            $table->decimal('telematics_health_score', 4, 1); // 0 - 100
            $table->decimal('calculated_buyback_price_usd', 15, 2); // 313.2 Deterministic formula
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('c2b_buyback_offers');
        Schema::dropIfExists('c2c_marketplace_escrows');
        Schema::dropIfExists('c2c_marketplace_listings');
    }
};
