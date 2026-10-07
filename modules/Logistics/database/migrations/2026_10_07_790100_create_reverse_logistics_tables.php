<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 79.1 Reverse logistics orders
        Schema::create('lgx_reverse_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reverse_code', 32)->unique();
            $table->string('event_trigger_type', 64); // store.return, auto.oil_used, resto.waste_bulk, mfg.scrap
            $table->string('source_reference_id', 64);
            $table->string('pickup_location', 128);
            $table->string('destination_facility', 128);
            $table->string('status', 32)->default('SCHEDULED'); // SCHEDULED, PICKED, IN_TRANSIT, RECEIVED, PROCESSED
            $table->string('manifest_number', 64)->unique();
            $table->string('custody_hash', 64);
            $table->bigInteger('total_appraised_value_idr')->default(0);
            $table->decimal('avoided_emissions_kg_co2', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['event_trigger_type', 'source_reference_id']);
        });

        // 79.1 Reverse items
        Schema::create('lgx_reverse_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reverse_order_id');
            $table->string('item_category', 32); // USED_OIL, COOKING_OIL_JELANTAH, SCRAP_METAL, E_WASTE, STORE_RETURN
            $table->string('description', 128);
            $table->decimal('quantity_kg_or_units', 10, 2);
            $table->string('unit_of_measure', 16); // KG, LITER, UNIT
            $table->string('recycling_target', 64); // BIODIESEL_REFINERY, LUBRICANT_RECYCLE, SMELTER, REFURBISH
            $table->bigInteger('appraised_unit_price_idr')->default(0);
            $table->bigInteger('subtotal_idr')->default(0);
            $table->timestamps();

            $table->foreign('reverse_order_id')->references('id')->on('lgx_reverse_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_reverse_items');
        Schema::dropIfExists('lgx_reverse_orders');
    }
};
