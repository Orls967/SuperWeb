<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfg_material_lots', function (Blueprint $table) {
            $table->unsignedBigInteger('source_reverse_shipment_id')->nullable()->unique()->after('source_ref');
            $table->string('grade_code', 16)->nullable()->after('lot_number');

            $table->foreign('source_reverse_shipment_id')->references('id')->on('lgx_shipments')->nullOnDelete();
        });

        Schema::create('mfg_recycled_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reverse_shipment_id')->unique();
            $table->uuid('material_lot_id')->unique();
            $table->string('material_code', 32);
            $table->string('grade_code', 16);
            $table->unsignedBigInteger('quantity_grams');
            $table->unsignedBigInteger('unit_price_per_kg_idr');
            $table->unsignedBigInteger('total_value_idr');
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->foreign('reverse_shipment_id')->references('id')->on('lgx_shipments')->cascadeOnDelete();
            $table->foreign('material_lot_id')->references('id')->on('mfg_material_lots')->cascadeOnDelete();
            $table->index(['material_code', 'grade_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_recycled_receipts');
        Schema::table('mfg_material_lots', function (Blueprint $table) {
            $table->dropForeign(['source_reverse_shipment_id']);
            $table->dropColumn(['source_reverse_shipment_id', 'grade_code']);
        });
    }
};

