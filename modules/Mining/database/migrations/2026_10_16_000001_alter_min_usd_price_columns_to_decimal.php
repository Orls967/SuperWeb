<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('min_offtaker_contracts', function (Blueprint $table): void {
            $table->decimal('base_lme_price_usd_per_ton', 14, 2)->change();
        });

        Schema::table('min_metals_trading_positions', function (Blueprint $table): void {
            $table->decimal('entry_price_usd', 14, 2)->change();
            $table->decimal('current_mark_price_usd', 14, 2)->change();
        });

        Schema::table('min_resource_command_snapshots', function (Blueprint $table): void {
            $table->decimal('live_commodity_price_usd', 14, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('min_offtaker_contracts', function (Blueprint $table): void {
            $table->double('base_lme_price_usd_per_ton')->change();
        });

        Schema::table('min_metals_trading_positions', function (Blueprint $table): void {
            $table->double('entry_price_usd')->change();
            $table->double('current_mark_price_usd')->change();
        });

        Schema::table('min_resource_command_snapshots', function (Blueprint $table): void {
            $table->double('live_commodity_price_usd')->default(0)->change();
        });
    }
};
