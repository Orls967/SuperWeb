<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('code', 64)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('food_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('food_brands')->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('name', 128);
            $table->string('category', 64)->default('General');
            $table->unsignedInteger('standard_package_grams')->default(1000);
            $table->timestamps();
        });

        Schema::create('food_batch_nutrition', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('mfg_agri_batches')->cascadeOnDelete();
            $table->foreignId('food_product_id')->constrained('food_products')->cascadeOnDelete();
            $table->unsignedInteger('calories_kcal')->default(0);
            $table->decimal('protein_grams', 6, 1)->default(0.0);
            $table->decimal('fat_grams', 6, 1)->default(0.0);
            $table->decimal('carbs_grams', 6, 1)->default(0.0);
            $table->unsignedInteger('sodium_mg')->default(0);
            $table->unsignedInteger('serving_size_grams')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_batch_nutrition');
        Schema::dropIfExists('food_products');
        Schema::dropIfExists('food_brands');
    }
};
