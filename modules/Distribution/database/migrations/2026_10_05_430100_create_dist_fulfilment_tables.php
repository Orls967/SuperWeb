<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 43.1–43.8 Distribusi: order & alokasi, pemenuhan/POD, sell-out,
// konsinyasi, retur/klaim, rebate, harga HET, stok kritis VMI.
return new class extends Migration
{
    public function up(): void
    {
        // 43.1 Order distributor: limit + ATP + alokasi + backorder.
        Schema::create('dist_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('DOR/{ENT}/YYYY-NNNNN');
            $table->uuid('distributor_id');
            $table->string('status', 24)->default('draft')->comment('draft, allocated, partial, shipped, delivered, cancelled, backordered');
            $table->bigInteger('subtotal_idr')->default(0);
            $table->bigInteger('discount_idr')->default(0);
            $table->bigInteger('ppn_idr')->default(0)->comment('PPN 11% simulasi');
            $table->bigInteger('total_idr')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->string('shipping_address')->nullable();
            $table->date('requested_date')->nullable();
            $table->string('allocation_strategy', 12)->default('priority')->comment('priority, fair_share');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
            $table->index(['status', 'requested_date']);
        });

        Schema::create('dist_order_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 60);
            $table->string('name_snapshot', 200);
            $table->decimal('qty', 18, 6);
            $table->decimal('allocated_qty', 18, 6)->default(0);
            $table->decimal('shipped_qty', 18, 6)->default(0);
            $table->decimal('unit_price_idr', 18, 2)->default(0);
            $table->bigInteger('line_total_idr')->default(0);
            $table->string('line_status', 16)->default('open')->comment('open, allocated, backorder, shipped, delivered, cancelled');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('dist_orders')->cascadeOnDelete();
            $table->index(['product_id', 'line_status']);
            $table->index(['order_id']);
        });

        // 43.2 Pemenuhan: shipment Logistics + POD + pengakuan penjualan.
        Schema::create('dist_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('DSP/{ENT}/YYYY-NNNNN');
            $table->uuid('order_id');
            $table->string('tracking_number', 60)->nullable();
            $table->string('mode', 16)->default('ltl')->comment('ftl, ltl, multimoda, courier');
            $table->string('status', 16)->default('planned')->comment('planned, picked, shipped, pod, cancelled');
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('pod_at')->nullable();
            $table->string('pod_name')->nullable();
            $table->string('pod_note')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('dist_orders')->cascadeOnDelete();
            $table->index(['status']);
        });

        Schema::create('dist_shipment_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('shipment_id');
            $table->unsignedBigInteger('order_line_id');
            $table->decimal('qty', 18, 6);
            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('dist_shipments')->cascadeOnDelete();
            $table->foreign('order_line_id')->references('id')->on('dist_order_lines')->cascadeOnDelete();
        });

        // Faktur pajak simulasi (nomor seri + PPN 11%).
        Schema::create('dist_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('FTR/{ENT}/YYYY-NNNNN');
            $table->string('tax_serial', 60)->unique()->comment('Nomor seri faktur pajak (SIMULASI)');
            $table->uuid('order_id');
            $table->uuid('distributor_id');
            $table->bigInteger('subtotal_idr')->default(0);
            $table->bigInteger('discount_idr')->default(0);
            $table->bigInteger('ppn_idr')->default(0)->comment('PPN 11% SIMULASI');
            $table->bigInteger('total_idr')->default(0);
            $table->string('status', 16)->default('issued')->comment('issued, settled, void');
            $table->date('issued_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('dist_orders')->cascadeOnDelete();
            $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
            $table->index(['distributor_id', 'status']);
        });

        // 43.3 Sell-out reporting + deteksi anomali.
        Schema::create('dist_sellout_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('distributor_id');
            $table->unsignedBigInteger('outlet_id');
            $table->date('period_date');
            $table->string('status', 16)->default('submitted')->comment('submitted, validated, rejected, flagged');
            $table->decimal('total_qty', 18, 6)->default(0);
            $table->bigInteger('total_value_idr')->default(0);
            $table->json('anomalies')->nullable()->comment('stuffing, diversion, price_violation');
            $table->text('notes')->nullable();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
            $table->foreign('outlet_id')->references('id')->on('dist_outlets')->cascadeOnDelete();
            $table->unique(['distributor_id', 'outlet_id', 'period_date']);
        });

        Schema::create('dist_sellout_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('report_id');
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 60);
            $table->decimal('qty', 18, 6);
            $table->bigInteger('unit_price_idr')->default(0);
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('dist_sellout_reports')->cascadeOnDelete();
            $table->index(['report_id', 'sku']);
        });

        // 43.4 Konsinyasi: stok milik prinsipal di lokasi distributor.
        Schema::create('dist_consignment_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('distributor_id');
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 60);
            $table->decimal('qty', 18, 6)->default(0);
            $table->decimal('qty_sold_unbilled', 18, 6)->default(0);
            $table->bigInteger('value_idr')->default(0);
            $table->date('last_reconciled_at')->nullable();
            $table->timestamps();

            $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
            $table->unique(['distributor_id', 'product_id']);
        });

        Schema::create('dist_consignment_sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('consignment_id');
            $table->date('sold_at');
            $table->decimal('qty', 18, 6);
            $table->bigInteger('unit_price_idr')->default(0);
            $table->string('status', 16)->default('reported')->comment('reported, invoiced');
            $table->uuid('invoice_id')->nullable();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('consignment_id')->references('id')->on('dist_consignment_stocks')->cascadeOnDelete();
            $table->index(['status', 'sold_at']);
        });

        // 43.5 Retur & klaim.
        Schema::create('dist_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('RDN/{ENT}/YYYY-NNNNN');
            $table->uuid('distributor_id');
            $table->uuid('order_id')->nullable();
            $table->string('reason', 24)->comment('expired, damaged, wrong_ship, overstock, quality');
            $table->string('disposition', 16)->default('restock')->comment('restock, quarantine, destroy');
            $table->decimal('qty', 18, 6);
            $table->bigInteger('amount_idr')->default(0);
            $table->string('status', 16)->default('requested')->comment('requested, approved, credited, rejected');
            $table->uuid('credit_note_invoice_id')->nullable();
            $table->text('evidence_note')->nullable();
            $table->date('requested_at');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
            $table->index(['status', 'reason']);
        });

        // 43.6 Rebate & insentif.
        Schema::create('dist_rebate_programs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->string('kind', 16)->default('volume')->comment('volume, growth, tiered');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->decimal('rate_percent', 8, 4)->default(0)->comment('Rate tetap (volume)');
            $table->decimal('threshold_qty', 18, 6)->default(0)->comment('Ambang tumbuh/volume');
            $table->json('tier_breaks')->nullable()->comment('Tingkat: [{min_qty, rate_percent}]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['kind', 'is_active']);
        });

        Schema::create('dist_rebate_accruals', function (Blueprint $table) {
            $table->id();
            $table->uuid('distributor_id');
            $table->unsignedBigInteger('program_id');
            $table->uuid('order_id')->nullable();
            $table->date('period');
            $table->bigInteger('base_amount_idr')->default(0);
            $table->decimal('rate_percent', 8, 4)->default(0);
            $table->bigInteger('rebate_amount_idr')->default(0);
            $table->string('status', 16)->default('accrued')->comment('accrued, settled, expired');
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
            $table->foreign('program_id')->references('id')->on('dist_rebate_programs')->cascadeOnDelete();
            $table->index(['period', 'status']);
        });

        // 43.7 Harga HET simulasi untuk cek price compliance.
        Schema::create('dist_het_prices', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 60)->unique();
            $table->bigInteger('het_idr')->comment('Harga Eceran Tertinggi (SIMULASI)');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();
        });

        // 43.8 Stok kritis distributor → saran replenishment (VMI).
        Schema::create('dist_stock_levels', function (Blueprint $table) {
            $table->id();
            $table->uuid('distributor_id');
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 60);
            $table->decimal('qty_on_hand', 18, 6)->default(0);
            $table->decimal('min_qty', 18, 6)->default(0);
            $table->decimal('max_qty', 18, 6)->default(0);
            $table->decimal('avg_daily_sales', 18, 6)->default(0);
            $table->decimal('suggested_order_qty', 18, 6)->default(0);
            $table->string('status', 16)->default('ok')->comment('ok, critical, reordered');
            $table->timestamps();

            $table->foreign('distributor_id')->references('id')->on('dist_distributors')->cascadeOnDelete();
            $table->unique(['distributor_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dist_stock_levels');
        Schema::dropIfExists('dist_het_prices');
        Schema::dropIfExists('dist_rebate_accruals');
        Schema::dropIfExists('dist_rebate_programs');
        Schema::dropIfExists('dist_returns');
        Schema::dropIfExists('dist_consignment_sales');
        Schema::dropIfExists('dist_consignment_stocks');
        Schema::dropIfExists('dist_sellout_lines');
        Schema::dropIfExists('dist_sellout_reports');
        Schema::dropIfExists('dist_invoices');
        Schema::dropIfExists('dist_shipment_lines');
        Schema::dropIfExists('dist_shipments');
        Schema::dropIfExists('dist_order_lines');
        Schema::dropIfExists('dist_orders');
    }
};
