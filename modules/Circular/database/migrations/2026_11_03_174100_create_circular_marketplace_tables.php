<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cir_auction_rounds', function (Blueprint $table): void {
            $table->id();
            $table->string('round_code', 64)->unique();
            $table->string('material_code', 64);
            $table->string('grade_code', 32)->default('A');
            $table->unsignedBigInteger('origin_location_id')->nullable();
            $table->unsignedBigInteger('destination_facility_id')->nullable();
            $table->string('status', 32)->default('open'); // open, closed, settled
            $table->unsignedBigInteger('clearing_price_per_kg_idr')->nullable();
            $table->unsignedBigInteger('cleared_volume_grams')->default(0);
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['material_code', 'grade_code', 'status']);
            $table->index('origin_location_id');
            $table->index('destination_facility_id');
        });

        Schema::create('cir_listings', function (Blueprint $table): void {
            $table->id();
            $table->string('listing_code', 64)->unique();
            $table->unsignedBigInteger('seller_party_id');
            $table->foreignId('auction_round_id')->nullable()->constrained('cir_auction_rounds')->nullOnDelete();
            $table->string('material_code', 64);
            $table->string('grade_code', 32)->default('A');
            $table->unsignedBigInteger('available_grams');
            $table->unsignedBigInteger('unit_price_per_kg_idr');
            $table->string('mode', 32)->default('fixed'); // fixed, auction
            $table->string('status', 32)->default('active'); // active, sold, closed
            $table->unsignedBigInteger('origin_location_id')->nullable();
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->timestamps();

            $table->index('seller_party_id');
            $table->index('origin_location_id');
            $table->index(['material_code', 'grade_code', 'status']);
        });

        Schema::create('cir_bids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auction_round_id')->constrained('cir_auction_rounds')->cascadeOnDelete();
            $table->unsignedBigInteger('buyer_party_id');
            $table->unsignedBigInteger('quantity_grams');
            $table->unsignedBigInteger('max_price_per_kg_idr');
            $table->unsignedBigInteger('allocated_grams')->default(0);
            $table->string('status', 32)->default('active'); // active, allocated, unfilled
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->timestamps();

            $table->index('buyer_party_id');
            $table->index(['auction_round_id', 'status']);
        });

        Schema::create('cir_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 64)->unique();
            $table->foreignId('listing_id')->constrained('cir_listings')->restrictOnDelete();
            $table->foreignId('auction_round_id')->nullable()->constrained('cir_auction_rounds')->nullOnDelete();
            $table->unsignedBigInteger('buyer_party_id');
            $table->unsignedBigInteger('seller_party_id');
            $table->string('material_code', 64);
            $table->string('grade_code', 32)->default('A');
            $table->unsignedBigInteger('ordered_grams');
            $table->unsignedBigInteger('accepted_grams')->nullable();
            $table->unsignedBigInteger('unit_price_per_kg_idr');
            $table->unsignedBigInteger('escrow_amount_idr');
            $table->unsignedBigInteger('refund_amount_idr')->default(0);
            $table->string('status', 32)->default('escrow_held'); // escrow_held, in_transit, received, settled, cancelled
            $table->unsignedBigInteger('origin_location_id')->nullable();
            $table->unsignedBigInteger('destination_facility_id')->nullable();
            $table->unsignedBigInteger('reverse_shipment_id')->nullable();
            $table->unsignedBigInteger('plant_receipt_id')->nullable();
            $table->string('idempotency_key', 128)->unique();
            $table->timestamps();

            $table->index('buyer_party_id');
            $table->index('seller_party_id');
            $table->index('origin_location_id');
            $table->index('destination_facility_id');
            $table->index('reverse_shipment_id');
            $table->index('plant_receipt_id');
            $table->index('status');
        });

        Schema::create('cir_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('cir_orders')->cascadeOnDelete();
            $table->string('line_type', 32)->default('material_charge');
            $table->string('material_code', 64);
            $table->string('grade_code', 32)->default('A');
            $table->unsignedBigInteger('quantity_grams');
            $table->unsignedBigInteger('unit_price_per_kg_idr');
            $table->unsignedBigInteger('subtotal_idr');
            $table->timestamps();
        });

        Schema::create('cir_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('cir_orders')->restrictOnDelete();
            $table->unsignedBigInteger('seller_party_id');
            $table->unsignedBigInteger('gross_amount_idr');
            $table->unsignedInteger('fee_bps')->default(250);
            $table->unsignedBigInteger('fee_amount_idr');
            $table->unsignedBigInteger('net_seller_amount_idr');
            $table->boolean('is_paid_out')->default(false);
            $table->string('idempotency_key', 128)->unique();
            $table->timestamp('settled_at');
            $table->timestamps();

            $table->index('seller_party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cir_settlements');
        Schema::dropIfExists('cir_order_lines');
        Schema::dropIfExists('cir_orders');
        Schema::dropIfExists('cir_bids');
        Schema::dropIfExists('cir_listings');
        Schema::dropIfExists('cir_auction_rounds');
    }
};
