<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Pemasok bahan baku
        Schema::create('resto_suppliers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('contact', 100);
            $table->unsignedInteger('terms_days')->default(30); // jatuh tempo pembayaran
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('rating')->default(5); // 1-5 bintang
            $table->timestamps();

            $table->index('is_active');
        });

        // 2. Surat Pesanan Pembelian (Purchase Orders)
        Schema::create('resto_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 50)->unique();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('resto_suppliers')->cascadeOnDelete();
            $table->string('status', 30)->default('draft'); // draft, sent, partially_received, received, cancelled
            $table->timestamp('expected_at')->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('ppn')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['outlet_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });

        // 3. Baris item Purchase Order
        Schema::create('resto_purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_id')->constrained('resto_purchase_orders')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('resto_ingredients')->cascadeOnDelete();
            $table->decimal('qty_ordered', 18, 6);
            $table->string('unit', 30)->default('kg');
            $table->decimal('qty_base_unit', 18, 6); // hasil konversi ke base unit
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->decimal('qty_received', 18, 6)->default(0);
            $table->timestamps();

            $table->index(['po_id', 'ingredient_id']);
        });

        // 4. Penerimaan Barang (Goods Receipts)
        Schema::create('resto_goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('po_id')->constrained('resto_purchase_orders')->cascadeOnDelete();
            $table->timestamp('received_at')->useCurrent();
            $table->foreignId('received_by')->constrained('users')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->string('quality', 30)->default('good'); // good, partial_reject
            $table->string('photo_ref')->nullable();
            $table->timestamps();

            $table->index(['po_id', 'received_at']);
        });

        // 5. Baris rincian penerimaan barang
        Schema::create('resto_goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('resto_goods_receipts')->cascadeOnDelete();
            $table->foreignId('po_line_id')->constrained('resto_purchase_order_lines')->cascadeOnDelete();
            $table->decimal('qty_received_base_unit', 18, 6);
            $table->decimal('unit_cost', 18, 6);
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->timestamps();

            $table->index('receipt_id');
        });

        // 6. Transfer stok antar outlet & dapur sentral
        Schema::create('resto_stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 50)->unique();
            $table->foreignId('from_outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->foreignId('to_outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->string('status', 30)->default('draft'); // draft, in_transit, received, discrepancy
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('lines')->nullable(); // array of items: ingredient_id/menu_item_id, qty_shipped, qty_received, unit_cost
            $table->decimal('qty_shipped', 18, 6)->default(0);
            $table->decimal('qty_received', 18, 6)->default(0);
            $table->text('variance_note')->nullable();
            $table->timestamps();

            $table->index(['from_outlet_id', 'status']);
            $table->index(['to_outlet_id', 'status']);
        });

        // 7. Stock Opname
        Schema::create('resto_stock_counts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 30)->default('draft'); // draft, submitted, approved, rejected
            $table->foreignId('counted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'date']);
            $table->index('status');
        });

        // 8. Baris Stock Opname
        Schema::create('resto_stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('count_id')->constrained('resto_stock_counts')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('resto_ingredients')->cascadeOnDelete();
            $table->decimal('system_qty', 18, 6)->default(0);
            $table->decimal('counted_qty', 18, 6)->default(0);
            $table->decimal('variance', 18, 6)->default(0); // counted - system
            $table->bigInteger('variance_value')->default(0); // signed monetary value
            $table->timestamps();

            $table->index(['count_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resto_stock_count_lines');
        Schema::dropIfExists('resto_stock_counts');
        Schema::dropIfExists('resto_stock_transfers');
        Schema::dropIfExists('resto_goods_receipt_lines');
        Schema::dropIfExists('resto_goods_receipts');
        Schema::dropIfExists('resto_purchase_order_lines');
        Schema::dropIfExists('resto_purchase_orders');
        Schema::dropIfExists('resto_suppliers');
    }
};
