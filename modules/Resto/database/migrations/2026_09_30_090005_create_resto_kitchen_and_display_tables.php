<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ingredient stock per outlet
        Schema::create('resto_ingredient_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('resto_ingredients')->cascadeOnDelete();
            $table->decimal('stock_base_unit', 18, 6)->default(0);
            $table->timestamps();

            $table->unique(['ingredient_id', 'outlet_id']);
        });

        // 2. Ingredient stock movements audit trail
        Schema::create('resto_ingredient_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('resto_ingredients')->cascadeOnDelete();
            $table->decimal('qty_base_unit', 18, 6); // signed (+ for add, - for deduct)
            $table->string('reason', 50); // production, purchase, adjustment, waste, transfer
            $table->nullableMorphs('source');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['outlet_id', 'ingredient_id']);
            $table->index('reason');
        });

        // 3. Production batches
        Schema::create('resto_production_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('resto_recipes')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('resto_menu_items')->nullOnDelete();
            $table->string('batch_no', 60)->unique();
            $table->integer('planned_portions');
            $table->integer('actual_portions');
            $table->timestamp('cooked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 30)->default('planned'); // planned, cooking, ready, on_display, depleted, discarded
            $table->unsignedBigInteger('cost_total')->default(0); // IDR integer
            $table->unsignedBigInteger('cost_per_portion')->default(0); // IDR integer
            $table->foreignId('produced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'status']);
        });

        // 4. Batch consumptions
        Schema::create('resto_batch_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('resto_production_batches')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('resto_ingredients')->cascadeOnDelete();
            $table->decimal('qty_base_unit', 18, 6);
            $table->decimal('unit_cost', 18, 6);
            $table->unsignedBigInteger('line_cost'); // IDR integer
            $table->timestamps();
        });

        // 5. Display trays (siklus etalase hidang khas Padang)
        Schema::create('resto_display_trays', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('resto_production_batches')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('resto_menu_items')->cascadeOnDelete();
            $table->integer('portions_remaining');
            $table->integer('recirculation_count')->default(0);
            $table->timestamp('placed_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->string('status', 30)->default('on_display'); // on_display, in_service, returned, discarded, depleted
            $table->unsignedBigInteger('cost_per_portion')->default(0);
            $table->timestamps();

            $table->index(['outlet_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resto_display_trays');
        Schema::dropIfExists('resto_batch_consumptions');
        Schema::dropIfExists('resto_production_batches');
        Schema::dropIfExists('resto_ingredient_movements');
        Schema::dropIfExists('resto_ingredient_stocks');
    }
};
