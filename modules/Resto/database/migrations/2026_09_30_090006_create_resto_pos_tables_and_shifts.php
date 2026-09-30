<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Shifts kasir
        Schema::create('resto_shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('opening_float')->default(0); // modal awal fisik
            $table->unsignedBigInteger('expected_cash')->default(0);
            $table->unsignedBigInteger('counted_cash')->nullable();
            $table->bigInteger('variance')->default(0); // signed: counted - expected
            $table->string('status', 20)->default('open'); // open, closed, reviewed
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'status']);
            $table->index(['cashier_id', 'status']);
        });

        // 2. Meja makan
        Schema::create('resto_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->string('code', 20); // A1, A2, VIP1, dll.
            $table->integer('seats')->default(4);
            $table->string('zone', 30)->default('indoor'); // indoor, outdoor, lesehan, vip
            $table->string('status', 20)->default('available'); // available, occupied, reserved, cleaning
            $table->timestamps();

            $table->unique(['outlet_id', 'code']);
            $table->index(['outlet_id', 'status']);
        });

        // 3. Sesi meja makan
        Schema::create('resto_table_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('resto_tables')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->integer('guest_count')->default(1);
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 20)->default('open'); // open, closing, closed, abandoned
            $table->timestamps();

            $table->index(['outlet_id', 'status']);
            $table->index(['table_id', 'status']);
        });

        // 4. Pesanan restoran (resto_orders)
        Schema::create('resto_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->string('number', 60)->unique(); // RSR-{OUTLET}-YYYYMMDD-XXXX
            $table->foreignId('table_session_id')->nullable()->constrained('resto_table_sessions')->nullOnDelete();
            $table->string('channel', 30)->default('dine_in'); // dine_in, takeaway, delivery, catering
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name', 100)->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('service_charge')->default(0);
            $table->unsignedBigInteger('tax_pb1')->default(0); // Pajang Resto PB1 10%
            $table->bigInteger('rounding')->default(0); // pembulatan ke Rp100 terdekat
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->string('payment_method', 30)->nullable(); // cash, wallet, voucher, points, split
            $table->string('status', 30)->default('open'); // open, awaiting_payment, paid, void, refunded
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('resto_shifts')->nullOnDelete();
            $table->string('idempotency_key', 100)->unique();
            $table->timestamp('client_created_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'status']);
            $table->index(['shift_id', 'status']);
            $table->index('created_at');
        });

        // 5. Item pesanan (resto_order_items)
        Schema::create('resto_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('resto_orders')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('resto_menu_items')->cascadeOnDelete();
            $table->foreignId('tray_id')->nullable()->constrained('resto_display_trays')->nullOnDelete();
            $table->string('name_snapshot');
            $table->unsignedBigInteger('unit_price_snapshot');
            $table->integer('qty')->default(1);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->string('source', 20)->default('pesan'); // hidang, pesan
            $table->string('consumed_state', 20)->default('consumed'); // presented, consumed, returned
            $table->unsignedBigInteger('cogs_snapshot')->default(0); // HPP snapshot
            $table->timestamps();

            $table->index(['order_id', 'consumed_state']);
        });

        // 6. Ringkasan penjualan harian (resto_daily_summaries)
        Schema::create('resto_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('resto_outlets')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('gross_sales')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('pb1')->default(0);
            $table->unsignedBigInteger('net_sales')->default(0);
            $table->unsignedBigInteger('cogs')->default(0);
            $table->unsignedBigInteger('waste_value')->default(0);
            $table->bigInteger('gross_margin')->default(0);
            $table->integer('transactions')->default(0);
            $table->integer('guests')->default(0);
            $table->unsignedBigInteger('avg_check')->default(0);
            $table->bigInteger('cash_variance')->default(0);
            $table->json('top_items')->nullable();
            $table->timestamps();

            $table->unique(['outlet_id', 'date']);
            $table->index(['date', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resto_daily_summaries');
        Schema::dropIfExists('resto_order_items');
        Schema::dropIfExists('resto_orders');
        Schema::dropIfExists('resto_table_sessions');
        Schema::dropIfExists('resto_tables');
        Schema::dropIfExists('resto_shifts');
    }
};
