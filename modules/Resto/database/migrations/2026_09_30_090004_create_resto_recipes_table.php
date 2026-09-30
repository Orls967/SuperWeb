<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resto_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->nullable()->constrained('resto_menu_items')->nullOnDelete();
            $table->string('sub_recipe_name')->nullable(); // e.g. "Bumbu Dasar Merah Padang", "Bumbu Gulai"
            $table->decimal('yield_qty', 18, 6)->default(1);
            $table->string('yield_unit', 30)->default('porsi'); // porsi, gram, liter
            $table->decimal('expected_portions', 18, 6)->default(1);
            $table->decimal('waste_percent', 5, 2)->default(0);
            $table->text('instructions')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('resto_recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('resto_recipes')->cascadeOnDelete();
            $table->string('line_type', 30)->default('ingredient'); // ingredient, sub_recipe
            $table->foreignId('ingredient_id')->nullable()->constrained('resto_ingredients')->nullOnDelete();
            $table->foreignId('sub_recipe_id')->nullable()->constrained('resto_recipes')->nullOnDelete();
            $table->decimal('qty_base_unit', 18, 6);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resto_recipe_lines');
        Schema::dropIfExists('resto_recipes');
    }
};
