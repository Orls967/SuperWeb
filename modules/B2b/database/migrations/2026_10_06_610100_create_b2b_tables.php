<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_wholesale_catalogs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku')->unique();
            $table->string('vendor_id');
            $table->string('product_name');
            $table->string('category');
            $table->bigInteger('base_price_idr');
            $table->integer('min_order_qty')->default(1);
            $table->json('tiered_pricing_matrix')->nullable(); // [{min_qty: 100, price_idr: 90000}]
            $table->integer('available_stock')->default(0);
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        Schema::create('b2b_rfqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rfq_number')->unique();
            $table->string('buyer_id');
            $table->string('vendor_id');
            $table->foreignUuid('catalog_id')->constrained('b2b_wholesale_catalogs')->cascadeOnDelete();
            $table->integer('requested_quantity');
            $table->bigInteger('target_price_idr')->nullable();
            $table->string('payment_terms')->default('TOP_30'); // CASH, TOP_30, TOP_60
            $table->string('status')->default('open'); // open, quoted, accepted, rejected, cancelled
            $table->bigInteger('agreed_price_idr')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_auctions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('lot_number')->unique();
            $table->string('seller_id');
            $table->string('asset_type'); // vehicle, factory_machine, surplus_inventory
            $table->string('asset_reference_id')->nullable();
            $table->string('title');
            $table->string('auction_type')->default('ENGLISH'); // ENGLISH, DUTCH
            $table->bigInteger('starting_bid_idr');
            $table->bigInteger('reserve_price_idr');
            $table->bigInteger('bid_increment_idr')->default(1000000);
            $table->bigInteger('current_highest_bid_idr')->default(0);
            $table->string('winning_bidder_id')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('anti_sniping_enabled')->default(true);
            $table->string('status')->default('draft'); // draft, active, ended, awarded, unsold
            $table->timestamps();
        });

        Schema::create('b2b_escrow_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('escrow_number')->unique();
            $table->string('reference_type'); // rfq_order, auction_deposit, auction_settlement
            $table->string('reference_id');
            $table->string('buyer_id');
            $table->string('seller_id');
            $table->bigInteger('deposit_amount_idr');
            $table->bigInteger('released_amount_idr')->default(0);
            $table->bigInteger('refunded_amount_idr')->default(0);
            $table->string('status')->default('held'); // held, partially_released, released, refunded
            $table->string('bast_document_id')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_escrow_accounts');
        Schema::dropIfExists('b2b_auctions');
        Schema::dropIfExists('b2b_rfqs');
        Schema::dropIfExists('b2b_wholesale_catalogs');
    }
};
