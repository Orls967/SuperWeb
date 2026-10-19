<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 81.1 Partisi & agregat per menit untuk query tick
        Schema::create('prc_price_tick_aggregates', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64);
            $table->dateTime('bucket_minute');
            $table->bigInteger('open_price_idr');
            $table->bigInteger('high_price_idr');
            $table->bigInteger('low_price_idr');
            $table->bigInteger('close_price_idr');
            $table->integer('tick_count')->default(1);
            $table->decimal('avg_demand_index', 6, 2)->default(1.00);
            $table->timestamps();

            $table->unique(['sku', 'bucket_minute'], 'prc_aggregates_sku_minute_unique');
        });

        // 81.3 Price waterfall audit log
        Schema::create('pric_price_waterfall_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64);
            $table->string('channel', 32)->default('STORE_B2C');
            $table->bigInteger('base_hpp_idr');
            $table->bigInteger('demand_surge_delta_idr')->default(0);
            $table->bigInteger('contract_override_delta_idr')->default(0);
            $table->bigInteger('guardrail_clamp_delta_idr')->default(0);
            $table->bigInteger('final_price_idr');
            $table->string('applied_driver', 32);
            $table->dateTime('created_at');

            $table->index(['sku', 'created_at']);
        });

        // 81.4 Dokumen immutable: perluasan frozen quotes dengan order_ref & channel
        Schema::table('prc_frozen_quotes', function (Blueprint $table) {
            $table->string('order_ref', 64)->nullable()->after('quote_hash');
            $table->string('channel', 32)->default('STORE_B2C')->after('order_ref');
            $table->dateTime('converted_at')->nullable()->after('locked_until');
            $table->json('metadata')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('prc_frozen_quotes', function (Blueprint $table) {
            $table->dropColumn(['order_ref', 'channel', 'converted_at', 'metadata']);
        });

        Schema::dropIfExists('pric_price_waterfall_logs');
        Schema::dropIfExists('prc_price_tick_aggregates');
    }
};
