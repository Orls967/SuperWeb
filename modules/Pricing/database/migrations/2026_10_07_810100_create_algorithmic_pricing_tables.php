<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 81.1 High-frequency price ticks
        Schema::create('prc_price_ticks', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64);
            $table->dateTime('tick_time');
            $table->bigInteger('price_idr');
            $table->decimal('demand_index', 6, 2)->default(1.0);
            $table->string('driver_source', 32); // DEMAND_SURGE, INVENTORY_SCARCITY, COMMODITY_FEED, OFF_PEAK
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index(['sku', 'tick_time']);
        });

        // 81.4 Immutable price freeze quotes/documents
        Schema::create('prc_frozen_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_code', 32)->unique();
            $table->string('sku', 64);
            $table->bigInteger('frozen_price_idr');
            $table->bigInteger('floor_price_idr');
            $table->bigInteger('ceiling_price_idr');
            $table->string('quote_hash', 64);
            $table->dateTime('locked_until');
            $table->string('status', 32)->default('LOCKED'); // LOCKED, EXPIRED, CONVERTED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prc_frozen_quotes');
        Schema::dropIfExists('prc_price_ticks');
    }
};
