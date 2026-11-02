<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prc_price_ticks') && ! Schema::hasTable('pric_price_ticks')) {
            Schema::rename('prc_price_ticks', 'pric_price_ticks');
        }

        if (Schema::hasTable('prc_price_tick_aggregates') && ! Schema::hasTable('pric_price_tick_aggregates')) {
            Schema::rename('prc_price_tick_aggregates', 'pric_price_tick_aggregates');
        }

        if (Schema::hasTable('prc_frozen_quotes') && ! Schema::hasTable('pric_frozen_quotes')) {
            Schema::rename('prc_frozen_quotes', 'pric_frozen_quotes');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pric_frozen_quotes') && ! Schema::hasTable('prc_frozen_quotes')) {
            Schema::rename('pric_frozen_quotes', 'prc_frozen_quotes');
        }

        if (Schema::hasTable('pric_price_tick_aggregates') && ! Schema::hasTable('prc_price_tick_aggregates')) {
            Schema::rename('pric_price_tick_aggregates', 'prc_price_tick_aggregates');
        }

        if (Schema::hasTable('pric_price_ticks') && ! Schema::hasTable('prc_price_ticks')) {
            Schema::rename('pric_price_ticks', 'prc_price_ticks');
        }
    }
};
