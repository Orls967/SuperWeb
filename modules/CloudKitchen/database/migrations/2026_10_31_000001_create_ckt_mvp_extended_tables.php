<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ckt_recipes')) {
            Schema::create('ckt_recipes', function (Blueprint $table) {
                $table->id();
                $table->string('recipe_code')->unique();
                $table->string('name');
                $table->string('brand_name');
                $table->unsignedBigInteger('selling_price_idr');
                $table->unsignedInteger('estimated_prep_minutes')->default(20);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ckt_recipe_ingredients')) {
            Schema::create('ckt_recipe_ingredients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_recipe_id')->constrained('ckt_recipes')->cascadeOnDelete();
                $table->foreignId('child_recipe_id')->nullable()->constrained('ckt_recipes')->nullOnDelete();
                $table->string('ingredient_code')->nullable();
                $table->unsignedInteger('quantity_required');
                $table->string('unit')->default('gram');
                $table->unsignedBigInteger('cost_per_unit_idr')->default(0);
                $table->timestamps();

                $table->index(['parent_recipe_id', 'ingredient_code']);
            });
        }

        if (! Schema::hasTable('ckt_orders')) {
            Schema::create('ckt_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_code')->unique();
                $table->foreignId('subscription_id')->constrained('ckt_catering_subscriptions')->cascadeOnDelete();
                $table->foreignId('kitchen_id')->constrained('ckt_kitchens');
                $table->foreignId('recipe_id')->nullable()->constrained('ckt_recipes')->nullOnDelete();
                $table->date('delivery_date');
                $table->string('meal_slot'); // lunch, dinner
                $table->string('status')->default('pending'); // pending, preparing, ready_for_pickup, in_transit, delivered, cancelled
                $table->unsignedBigInteger('meal_cost_idr')->default(0);
                $table->string('courier_batch_ref')->nullable();
                $table->string('idempotency_key')->unique();
                $table->string('payload_hash')->nullable();
                $table->timestamps();

                $table->index(['subscription_id', 'delivery_date', 'status']);
                $table->index(['kitchen_id', 'delivery_date']);
            });
        }

        if (! Schema::hasTable('ckt_ingredient_consumptions')) {
            Schema::create('ckt_ingredient_consumptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('ckt_orders')->cascadeOnDelete();
                $table->string('ingredient_code');
                $table->unsignedInteger('quantity_consumed');
                $table->unsignedBigInteger('unit_cost_idr');
                $table->unsignedBigInteger('total_cogs_idr');
                $table->string('movement_idempotency_key')->unique();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['order_id', 'ingredient_code']);
            });
        }

        if (! Schema::hasTable('ckt_payroll_deductions')) {
            Schema::create('ckt_payroll_deductions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->constrained('ckt_catering_subscriptions')->cascadeOnDelete();
                $table->string('payroll_period');
                $table->unsignedBigInteger('amount_idr');
                $table->string('idempotency_key')->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ckt_payroll_deductions');
        Schema::dropIfExists('ckt_ingredient_consumptions');
        Schema::dropIfExists('ckt_orders');
        Schema::dropIfExists('ckt_recipe_ingredients');
        Schema::dropIfExists('ckt_recipes');
    }
};
