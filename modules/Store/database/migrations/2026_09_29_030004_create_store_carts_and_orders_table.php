<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('store_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('store_carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->integer('qty')->default(1);
            $table->decimal('price_snapshot', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending_payment')->index();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('shipping_fee', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->json('shipping_address')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('store_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->string('name_snapshot');
            $table->decimal('price_snapshot', 15, 2)->default(0);
            $table->integer('qty')->default(1);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->unsignedBigInteger('reservation_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_order_items');
        Schema::dropIfExists('store_orders');
        Schema::dropIfExists('store_cart_items');
        Schema::dropIfExists('store_carts');
    }
};
