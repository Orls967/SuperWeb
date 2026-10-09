<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_commodity_shipment_chains', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_chain_code')->unique();
            $table->string('commodity_lot_id');
            $table->decimal('handoff_quantity_tons', 12, 4);
            $table->boolean('quantity_balanced')->default(true); // 386.4
            $table->boolean('is_lot_isolated')->default(false); // 386.2 & 386.5 Edge case
            $table->boolean('lc_partial_hold_applied')->default(false);
            $table->boolean('unaffected_shipments_proceed')->default(true);
            $table->timestamps();
        });

        Schema::create('global_commodity_hedging_books', function (Blueprint $table) {
            $table->id();
            $table->string('hedge_code')->unique();
            $table->decimal('physical_exposure_tons', 12, 4);
            $table->decimal('hedged_position_tons', 12, 4);
            $table->boolean('over_hedged_prevented')->default(true); // 386.3, 386.4, 386.6 Risk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_commodity_hedging_books');
        Schema::dropIfExists('global_commodity_shipment_chains');
    }
};
