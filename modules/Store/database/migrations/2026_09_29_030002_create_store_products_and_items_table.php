<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable()->index();
            $table->json('specifications')->nullable();
            $table->timestamps();
        });

        Schema::create('store_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->nullableMorphs('productable');
            $table->foreignId('category_id')->nullable()->constrained('store_categories')->nullOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('compare_at_price', 15, 2)->nullable();
            $table->integer('cached_stock')->default(0);
            $table->boolean('is_listed')->default(false)->index();
            $table->boolean('is_car')->default(false)->index();
            $table->integer('weight_gram')->default(500);
            $table->json('images')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_products');
        Schema::dropIfExists('store_items');
    }
};
