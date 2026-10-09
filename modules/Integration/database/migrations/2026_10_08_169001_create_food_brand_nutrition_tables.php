<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 169.1: Brand product formulations & allergen definitions
        Schema::create('food_brand_formulations', function (Blueprint $table) {
            $table->id();
            $table->string('formulation_code')->unique();
            $table->string('brand_name');
            $table->string('product_name');
            $table->integer('version')->default(1);
            $table->json('contained_allergens'); // e.g. ["PEANUTS", "DAIRY", "GLUTEN"]
            $table->boolean('is_immutable')->default(true);
            $table->timestamps();
        });

        // 169.2: Private label production contracts (customer-owned materials separated)
        Schema::create('food_private_label_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->string('client_entity_code'); // RESTO, HOTEL, HOSPITAL, RETAIL
            $table->string('formulation_code');
            $table->decimal('customer_owned_material_qty', 18, 2);
            $table->decimal('conversion_service_fee', 18, 2);
            $table->boolean('ownership_segregated')->default(true);
            $table->timestamps();
        });

        // 169.3: Nutrition & dietary meal orders with allergen conflict checks
        Schema::create('food_dietary_meal_orders', function (Blueprint $table) {
            $table->id();
            $table->string('meal_order_code')->unique();
            $table->string('institution_type'); // HOSPITAL, CAMPUS, RESTO
            $table->unsignedBigInteger('beneficiary_id');
            $table->string('formulation_code');
            $table->json('patient_allergies');
            $table->boolean('allergen_blocked')->default(false);
            $table->string('status')->default('APPROVED'); // APPROVED, BLOCKED
            $table->timestamps();
        });

        // 169.4: Inventory batches with FEFO tracking
        Schema::create('food_inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code')->unique();
            $table->string('formulation_code');
            $table->integer('available_units');
            $table->date('expiration_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_inventory_batches');
        Schema::dropIfExists('food_dietary_meal_orders');
        Schema::dropIfExists('food_private_label_orders');
        Schema::dropIfExists('food_brand_formulations');
    }
};
