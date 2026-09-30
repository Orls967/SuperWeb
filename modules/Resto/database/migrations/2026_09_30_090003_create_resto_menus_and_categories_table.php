<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resto_menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('resto_menu_categories')->nullOnDelete();
            $table->string('name');
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('resto_menu_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')->constrained('resto_menu_categories')->cascadeOnDelete();
            $table->string('sku', 50)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('service_style', 30)->default('hidang'); // hidang, pesan, minuman, paket
            $table->bigInteger('base_price')->default(0); // IDR
            $table->bigInteger('takeaway_price')->default(0); // IDR (bungkus can be different)
            $table->boolean('is_active')->default(true);
            $table->boolean('is_halal_certified')->default(true);
            $table->integer('spice_level')->default(1);
            $table->json('images')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('resto_menu_item_outlet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('resto_menu_items')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->bigInteger('price_override')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['menu_item_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resto_menu_item_outlet');
        Schema::dropIfExists('resto_menu_items');
        Schema::dropIfExists('resto_menu_categories');
    }
};
