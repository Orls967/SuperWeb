<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 181.1 & 181.2: Apparel factories & ESG labor compliance audit
        Schema::create('fsh_supplier_factories', function (Blueprint $table) {
            $table->id();
            $table->string('factory_code')->unique();
            $table->string('factory_name');
            $table->boolean('labor_standard_approved')->default(false);
            $table->boolean('esg_audit_passed')->default(false);
            $table->timestamps();
        });

        // 181.1 & 181.3: Fashion purchase orders with size-color SKU matrices
        Schema::create('fsh_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_code')->unique();
            $table->string('factory_code');
            $table->string('collection_name');
            $table->integer('total_order_units');
            $table->string('status')->default('ISSUED'); // ISSUED, BLOCKED, COMPLETED
            $table->timestamps();
        });

        // 181.1: Size-color SKU item breakdown
        Schema::create('fsh_order_sku_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->string('po_code');
            $table->string('sku_code'); // e.g., TSHIRT-BLK-M
            $table->string('color');
            $table->string('size');
            $table->integer('units_allocated');
            $table->timestamps();
        });

        // 181.4: Retail markdown promotions with margin approval
        Schema::create('fsh_markdown_plans', function (Blueprint $table) {
            $table->id();
            $table->string('markdown_code')->unique();
            $table->string('collection_name');
            $table->decimal('original_price_idr', 18, 2);
            $table->decimal('discount_pct', 5, 2);
            $table->decimal('discounted_price_idr', 18, 2);
            $table->decimal('unit_cost_idr', 18, 2);
            $table->boolean('margin_approved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fsh_markdown_plans');
        Schema::dropIfExists('fsh_order_sku_breakdowns');
        Schema::dropIfExists('fsh_purchase_orders');
        Schema::dropIfExists('fsh_supplier_factories');
    }
};
