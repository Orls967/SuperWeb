<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 115.1 Creators (DJs, artists, bands, brands)
        Schema::create('ven_creators', function (Blueprint $table) {
            $table->id();
            $table->string('creator_code', 32)->unique();
            $table->string('stage_name', 128);
            $table->string('genre', 64);
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();
        });

        // 115.1 Content Assets with Hash & License
        Schema::create('ven_content_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 32)->unique();
            $table->unsignedBigInteger('creator_id');
            $table->string('title', 160);
            $table->string('media_type', 32); // AUDIO_TRACK, VIDEO_SET, PHOTO_GALLERY
            $table->string('content_hash', 64);
            $table->string('license_type', 32)->default('EXCLUSIVE'); // EXCLUSIVE, NON_EXCLUSIVE
            $table->timestamps();

            $table->foreign('creator_id')->references('id')->on('ven_creators')->cascadeOnDelete();
        });

        // 115.1 & 115.2 Streaming Rights Contracts & Payouts with Dispute Hold Window
        Schema::create('ven_rights_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code', 32)->unique();
            $table->unsignedBigInteger('creator_id');
            $table->decimal('royalty_share_percent', 5, 2); // e.g. 70.0%
            $table->integer('payout_hold_window_days')->default(14); // 14-day hold for claim window
            $table->bigInteger('accrued_royalties_idr')->default(0);
            $table->bigInteger('held_royalties_idr')->default(0);
            $table->bigInteger('paid_royalties_idr')->default(0);
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();

            $table->foreign('creator_id')->references('id')->on('ven_creators')->cascadeOnDelete();
        });

        // 115.5 Artist Merch Sales Split
        Schema::create('ven_merch_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_code', 32)->unique();
            $table->unsignedBigInteger('creator_id');
            $table->string('item_name', 128);
            $table->bigInteger('total_sale_price_idr');
            $table->bigInteger('artist_share_idr');
            $table->bigInteger('platform_share_idr');
            $table->string('status', 32)->default('SETTLED');
            $table->timestamps();

            $table->foreign('creator_id')->references('id')->on('ven_creators')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_merch_sales');
        Schema::dropIfExists('ven_rights_contracts');
        Schema::dropIfExists('ven_content_assets');
        Schema::dropIfExists('ven_creators');
    }
};
