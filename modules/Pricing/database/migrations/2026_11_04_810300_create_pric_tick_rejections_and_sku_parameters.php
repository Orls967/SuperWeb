<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pric_tick_rejections')) {
            Schema::create('pric_tick_rejections', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 64)->index();
                $table->dateTime('slot_time');
                $table->bigInteger('attempted_price_idr');
                $table->bigInteger('base_price_idr');
                $table->bigInteger('last_price_idr');
                $table->bigInteger('raw_price_idr');
                $table->bigInteger('bounded_price_idr');
                $table->bigInteger('floor_price_idr');
                $table->bigInteger('ceiling_price_idr');
                $table->bigInteger('het_price_idr');
                $table->string('rejection_reason', 64); // BELOW_FLOOR, ABOVE_CEILING, ABOVE_HET
                $table->string('idempotency_key', 64)->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pric_sku_parameters')) {
            Schema::create('pric_sku_parameters', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 64)->unique();
                $table->string('category', 64)->default('general');
                $table->bigInteger('base_price_idr');
                $table->bigInteger('floor_price_idr');
                $table->bigInteger('ceiling_price_idr');
                $table->bigInteger('het_price_idr');
                $table->integer('price_step_idr')->default(50);
                $table->integer('elasticity_bps')->default(10000); // 1.0 = 10000 bps
                $table->boolean('is_perishable')->default(false);
                $table->integer('stock_cover_days')->nullable();
                $table->integer('demand_ratio_bps')->nullable();
                $table->integer('shelf_life_ratio_bps')->nullable();
                $table->integer('season_factor_bps')->default(0);
                $table->integer('occupancy_factor_bps')->default(0);
                $table->integer('version')->default(1);
                $table->timestamps();
            });
        }

        Schema::table('pric_price_ticks', function (Blueprint $table) {
            if (! Schema::hasColumn('pric_price_ticks', 'status')) {
                $table->string('status', 32)->default('normal')->after('driver_source');
            }
            if (! Schema::hasColumn('pric_price_ticks', 'base_price_idr')) {
                $table->bigInteger('base_price_idr')->nullable()->after('price_idr');
            }
            if (! Schema::hasColumn('pric_price_ticks', 'raw_price_idr')) {
                $table->bigInteger('raw_price_idr')->nullable()->after('base_price_idr');
            }
            if (! Schema::hasColumn('pric_price_ticks', 'slot_time')) {
                $table->dateTime('slot_time')->nullable()->after('tick_time')->index();
            }
            if (! Schema::hasColumn('pric_price_ticks', 'factor_total_bps')) {
                $table->integer('factor_total_bps')->nullable()->after('raw_price_idr');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pric_price_ticks', function (Blueprint $table) {
            if (Schema::hasColumn('pric_price_ticks', 'factor_total_bps')) {
                $table->dropColumn('factor_total_bps');
            }
            if (Schema::hasColumn('pric_price_ticks', 'slot_time')) {
                $table->dropColumn('slot_time');
            }
            if (Schema::hasColumn('pric_price_ticks', 'raw_price_idr')) {
                $table->dropColumn('raw_price_idr');
            }
            if (Schema::hasColumn('pric_price_ticks', 'base_price_idr')) {
                $table->dropColumn('base_price_idr');
            }
            if (Schema::hasColumn('pric_price_ticks', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::dropIfExists('pric_sku_parameters');
        Schema::dropIfExists('pric_tick_rejections');
    }
};
