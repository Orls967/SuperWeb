<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resto_ingredients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('sku', 50)->unique();
            $table->string('name');
            $table->string('base_unit', 20)->default('gram'); // gram, ml, pcs
            $table->string('category', 30); // protein, sayur, bumbu, beras, minuman, kemasan
            $table->boolean('is_perishable')->default(false);
            $table->integer('shelf_life_hours')->nullable();
            $table->decimal('min_stock_base_unit', 18, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('resto_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->nullable()->constrained('resto_ingredients')->nullOnDelete();
            $table->string('from_unit', 30); // kg, liter, ikat, butir, etc.
            $table->decimal('to_base_factor', 18, 6); // e.g. 1000 for kg->gram
            $table->string('label', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('resto_ingredient_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('resto_ingredients')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->decimal('moving_avg_cost_per_base_unit', 18, 6)->default(0);
            $table->decimal('last_purchase_cost', 18, 6)->default(0);
            $table->timestamps();

            $table->unique(['ingredient_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resto_ingredient_costs');
        Schema::dropIfExists('resto_unit_conversions');
        Schema::dropIfExists('resto_ingredients');
    }
};
