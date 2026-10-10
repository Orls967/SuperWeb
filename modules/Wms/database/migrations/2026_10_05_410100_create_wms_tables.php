<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 41.1–41.7 Gudang & pusat distribusi: hirarki, stok bin, tugas,
// transfer in-transit, cycle count, replenishment/slotting, dock & packing.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wms_warehouses')) {
            Schema::create('wms_warehouses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 40)->unique();
                $table->string('name', 160);
                $table->string('kind', 24)->default('dc')->comment('dc, raw, fg, quarantine, transit, consignment, reefer');
                $table->string('address')->nullable();
                $table->string('city', 60)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['kind', 'is_active']);
            });
        }

        if (! Schema::hasTable('wms_zones')) {
            Schema::create('wms_zones', function (Blueprint $table) {
                $table->id();
                $table->uuid('warehouse_id');
                $table->string('code', 40);
                $table->string('name', 160);
                $table->string('kind', 24)->default('storage')->comment('putaway, storage, pick, staging, quarantine, dock');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->unique(['warehouse_id', 'code']);
            });
        }

        if (! Schema::hasTable('wms_racks')) {
            Schema::create('wms_racks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('zone_id');
                $table->string('code', 40);
                $table->string('name', 120)->nullable();
                $table->timestamps();

                $table->foreign('zone_id')->references('id')->on('wms_zones')->cascadeOnDelete();
                $table->unique(['zone_id', 'code']);
            });
        }

        if (! Schema::hasTable('wms_bins')) {
            Schema::create('wms_bins', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rack_id');
                $table->string('code', 40);
                $table->unsignedInteger('capacity_units')->default(0)->comment('0 = tanpa batas');
                $table->boolean('is_pick_face')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('rack_id')->references('id')->on('wms_racks')->cascadeOnDelete();
                $table->unique(['rack_id', 'code']);
                $table->index(['is_pick_face', 'is_active']);
            });
        }

        // 41.2 Stok per bin/lot/serial/status di atas InventoryService.
        if (! Schema::hasTable('wms_bin_stocks')) {
            Schema::create('wms_bin_stocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bin_id');
                $table->unsignedBigInteger('product_id');
                $table->string('lot_number', 60)->nullable();
                $table->string('serial_number', 80)->nullable();
                $table->string('status', 16)->default('available')->comment('available, quarantine, blocked');
                $table->decimal('qty', 18, 6)->default(0);
                $table->timestamps();

                $table->foreign('bin_id')->references('id')->on('wms_bins')->cascadeOnDelete();
                $table->unique(['bin_id', 'product_id', 'lot_number', 'serial_number', 'status'], 'wms_bin_stocks_bin_prod_lot_sn_stat_uniq');
                $table->index(['product_id', 'status']);
            });
        }

        // 41.3 Tugas gudang: putaway / pick / pack / stage / replenish.
        if (! Schema::hasTable('wms_tasks')) {
            Schema::create('wms_tasks', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 16)->comment('putaway, pick, pack, stage, replenish');
                $table->string('status', 16)->default('open')->comment('open, done, cancelled');
                $table->string('priority', 8)->default('normal')->comment('low, normal, high');
                $table->unsignedBigInteger('bin_id')->nullable();
                $table->unsignedBigInteger('product_id');
                $table->string('lot_number', 60)->nullable();
                $table->decimal('qty', 18, 6);
                $table->string('source_ref', 80)->nullable()->comment('PO/transfer/order/task induk');
                $table->unsignedBigInteger('wave_id')->nullable();
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('bin_id')->references('id')->on('wms_bins')->nullOnDelete();
                $table->index(['kind', 'status', 'priority']);
                $table->index(['product_id', 'status']);
            });
        }

        // Wave picking (41.3).
        if (! Schema::hasTable('wms_waves')) {
            Schema::create('wms_waves', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique()->comment('WAVE/{ENT}/YYYY-NNNNN');
                $table->string('status', 16)->default('open')->comment('open, released, closed');
                $table->string('strategy', 16)->default('fifo')->comment('fifo, fefe, zone, batch');
                $table->text('notes')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
            });
        }

        // 41.4 Transfer antar-gudang & in-transit + cross-dock.
        if (! Schema::hasTable('wms_transfers')) {
            Schema::create('wms_transfers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('number', 40)->unique()->comment('TRF/{ENT}/YYYY-NNNNN');
                $table->uuid('from_warehouse_id');
                $table->uuid('to_warehouse_id');
                $table->string('status', 16)->default('draft')->comment('draft, in_transit, received, cancelled');
                $table->boolean('cross_dock')->default(false)->comment('Inbound langsung ke outbound');
                $table->string('tracking_number', 60)->nullable()->comment('Resi Logistics (41.7)');
                $table->unsignedBigInteger('appointment_id')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('from_warehouse_id')->references('id')->on('wms_warehouses');
                $table->foreign('to_warehouse_id')->references('id')->on('wms_warehouses');
                $table->index(['status']);
            });
        }

        if (! Schema::hasTable('wms_transfer_lines')) {
            Schema::create('wms_transfer_lines', function (Blueprint $table) {
                $table->id();
                $table->uuid('transfer_id');
                $table->unsignedBigInteger('product_id');
                $table->string('lot_number', 60)->nullable();
                $table->decimal('qty', 18, 6);
                $table->string('status', 16)->default('open')->comment('open, picked, received');
                $table->timestamps();

                $table->foreign('transfer_id')->references('id')->on('wms_transfers')->cascadeOnDelete();
                $table->index(['product_id', 'status']);
            });
        }

        // 41.5 Cycle counting & penyesuaian (approval).
        if (! Schema::hasTable('wms_cycle_counts')) {
            Schema::create('wms_cycle_counts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('number', 40)->unique()->comment('CC/{ENT}/YYYY-NNNNN');
                $table->uuid('warehouse_id');
                $table->string('status', 24)->default('draft')->comment('draft, pending_approval, approved, applied, rejected');
                $table->decimal('system_qty', 18, 6)->default(0);
                $table->decimal('counted_qty', 18, 6)->default(0);
                $table->decimal('variance_qty', 18, 6)->default(0);
                $table->bigInteger('variance_value_idr')->default(0);
                $table->decimal('accuracy_percent', 8, 4)->default(100)->comment('KPI akurasi stok');
                $table->unsignedBigInteger('approval_id')->nullable();
                $table->foreignId('counted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses');
                $table->index(['status']);
            });
        }

        if (! Schema::hasTable('wms_cycle_count_lines')) {
            Schema::create('wms_cycle_count_lines', function (Blueprint $table) {
                $table->id();
                $table->uuid('count_id');
                $table->unsignedBigInteger('bin_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('system_qty', 18, 6)->default(0);
                $table->decimal('counted_qty', 18, 6)->default(0);
                $table->decimal('variance_qty', 18, 6)->default(0);
                $table->string('lot_number', 60)->nullable();
                $table->timestamps();

                $table->foreign('count_id')->references('id')->on('wms_cycle_counts')->cascadeOnDelete();
                $table->foreign('bin_id')->references('id')->on('wms_bins');
                $table->index(['count_id', 'product_id']);
            });
        }

        // 41.6 Replenishment pick-face & slotting ABC.
        if (! Schema::hasTable('wms_replenishments')) {
            Schema::create('wms_replenishments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bin_id')->comment('Pick face');
                $table->unsignedBigInteger('product_id');
                $table->decimal('min_qty', 18, 6)->default(0);
                $table->decimal('max_qty', 18, 6)->default(0);
                $table->decimal('current_qty', 18, 6)->default(0);
                $table->decimal('suggested_qty', 18, 6)->default(0);
                $table->string('status', 16)->default('open')->comment('open, ordered, done');
                $table->timestamps();

                $table->foreign('bin_id')->references('id')->on('wms_bins')->cascadeOnDelete();
                $table->unique(['bin_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('wms_slottings')) {
            Schema::create('wms_slottings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->unique();
                $table->string('abc_class', 4)->default('C')->comment('A, B, C');
                $table->decimal('annual_value_idr', 20, 2)->default(0);
                $table->unsignedBigInteger('suggested_zone_id')->nullable();
                $table->timestamps();
            });
        }

        // 41.7 Dock appointment (inbound/outbound).
        if (! Schema::hasTable('wms_dock_appointments')) {
            Schema::create('wms_dock_appointments', function (Blueprint $table) {
                $table->id();
                $table->uuid('warehouse_id');
                $table->string('direction', 8)->comment('in, out');
                $table->string('reference', 80)->comment('PO/transfer/shipment ref');
                $table->timestamp('window_start');
                $table->timestamp('window_end');
                $table->string('status', 16)->default('scheduled')->comment('scheduled, arrived, done, cancelled');
                $table->string('carrier', 120)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('warehouse_id')->references('id')->on('wms_warehouses')->cascadeOnDelete();
                $table->index(['warehouse_id', 'window_start']);
            });
        }

        // 41.7 Packing list + label resi.
        if (! Schema::hasTable('wms_packing_lists')) {
            Schema::create('wms_packing_lists', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('number', 40)->unique()->comment('PL/{ENT}/YYYY-NNNNN');
                $table->uuid('transfer_id')->nullable();
                $table->unsignedBigInteger('appointment_id')->nullable();
                $table->json('items')->comment('product, qty, lot, serial');
                $table->string('tracking_number', 60)->nullable();
                $table->string('status', 16)->default('draft')->comment('draft, printed, shipped');
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('transfer_id')->references('id')->on('wms_transfers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_packing_lists');
        Schema::dropIfExists('wms_dock_appointments');
        Schema::dropIfExists('wms_slottings');
        Schema::dropIfExists('wms_replenishments');
        Schema::dropIfExists('wms_cycle_count_lines');
        Schema::dropIfExists('wms_cycle_counts');
        Schema::dropIfExists('wms_transfer_lines');
        Schema::dropIfExists('wms_transfers');
        Schema::dropIfExists('wms_waves');
        Schema::dropIfExists('wms_tasks');
        Schema::dropIfExists('wms_bin_stocks');
        Schema::dropIfExists('wms_bins');
        Schema::dropIfExists('wms_racks');
        Schema::dropIfExists('wms_zones');
        Schema::dropIfExists('wms_warehouses');
    }
};
