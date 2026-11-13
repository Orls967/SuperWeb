<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wms_bin_stocks')) {
            Schema::table('wms_bin_stocks', function (Blueprint $table) {
                if (! Schema::hasColumn('wms_bin_stocks', 'qty_base')) {
                    $table->bigInteger('qty_base')->default(0)->after('qty');
                }
                if (! Schema::hasColumn('wms_bin_stocks', 'lot_number')) {
                    $table->string('lot_number', 64)->nullable()->index()->after('qty_base');
                }
                if (! Schema::hasColumn('wms_bin_stocks', 'expiry_date')) {
                    $table->date('expiry_date')->nullable()->index()->after('lot_number');
                }
                if (! Schema::hasColumn('wms_bin_stocks', 'serial_number')) {
                    $table->string('serial_number', 64)->nullable()->index()->after('expiry_date');
                }
                if (! Schema::hasColumn('wms_bin_stocks', 'stock_status')) {
                    $table->string('stock_status', 24)->default('available')->index()->after('serial_number');
                }
                if (! Schema::hasColumn('wms_bin_stocks', 'quarantine_reason')) {
                    $table->string('quarantine_reason', 255)->nullable()->after('stock_status');
                }
                if (! Schema::hasColumn('wms_bin_stocks', 'quarantined_at')) {
                    $table->timestamp('quarantined_at')->nullable()->after('quarantine_reason');
                }
                if (! Schema::hasColumn('wms_bin_stocks', 'released_at')) {
                    $table->timestamp('released_at')->nullable()->after('quarantined_at');
                }
            });
        }

        if (! Schema::hasTable('wms_stock_reservations')) {
            Schema::create('wms_stock_reservations', function (Blueprint $table) {
                $table->id();
                $table->string('warehouse_id', 64)->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('bin_id')->nullable()->index();
                $table->string('lot_number', 64)->nullable();
                $table->unsignedBigInteger('qty_base');
                $table->string('reference_type', 64);
                $table->string('reference_id', 64);
                $table->string('status', 24)->default('active')->index(); // active, committed, released, expired
                $table->timestamp('expires_at')->index();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('store_products')->cascadeOnDelete();
                $table->foreign('bin_id')->references('id')->on('wms_bins')->nullOnDelete();
                $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['reference_type', 'reference_id'], 'wms_res_ref_idx');
            });
        }

        if (! Schema::hasTable('wms_receiving_receipts')) {
            Schema::create('wms_receiving_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('receipt_number', 64)->unique();
                $table->string('warehouse_id', 64)->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('expected_qty_base');
                $table->unsignedBigInteger('received_qty_base');
                $table->integer('variance_bps');
                $table->string('tolerance_decision', 32); // within_tolerance, over_receipt, short_receipt
                $table->string('lot_number', 64)->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('status', 32)->default('pending_putaway');
                $table->unsignedBigInteger('received_by_user_id')->nullable();
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('store_products')->cascadeOnDelete();
                $table->foreign('received_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('wms_utilization_snapshots')) {
            Schema::create('wms_utilization_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('warehouse_id', 64)->index();
                $table->unsignedBigInteger('zone_id')->nullable()->index();
                $table->date('snapshot_date')->index();
                $table->unsignedInteger('total_bins');
                $table->unsignedInteger('occupied_bins');
                $table->unsignedInteger('utilization_bps');
                $table->unsignedInteger('dead_stock_sku_count')->default(0);
                $table->unsignedInteger('slotting_mismatch_count')->default(0);
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->foreign('zone_id')->references('id')->on('wms_zones')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_utilization_snapshots');
        Schema::dropIfExists('wms_receiving_receipts');
        Schema::dropIfExists('wms_stock_reservations');
    }
};
