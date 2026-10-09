<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 37.1 Order produksi: state machine + nomor gapless (26.8).
        Schema::create('mfg_production_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('MPO/{ENT}/YYYY-NNNNN (26.8)');
            $table->uuid('material_id');
            $table->uuid('plant_id')->nullable();
            $table->uuid('planned_order_id')->nullable()->comment('Dari MRP (36.3/36.5)');
            $table->uuid('routing_id')->nullable();
            $table->string('kind', 16)->default('standard')->comment('standard, rework, subcontract');
            $table->decimal('qty', 18, 6)->comment('Qty target output');
            $table->decimal('qty_completed', 18, 6)->default(0);
            $table->string('status', 24)->default('planned')->comment('planned, released, in_progress, completed, closed, cancelled');
            $table->date('release_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('scrap_tolerance_percent', 8, 4)->default(2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('mfg_plants')->nullOnDelete();
            $table->foreign('planned_order_id')->references('id')->on('mfg_planned_orders')->nullOnDelete();
            $table->index(['status', 'due_date']);
            $table->index(['material_id', 'status']);
        });

        // Lot material (FIFO/FEFO) — dipakai issue 37.2 & FG receipt 37.5.
        Schema::create('mfg_material_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_id');
            $table->string('lot_number', 60);
            $table->decimal('qty', 18, 6)->default(0);
            $table->date('expiry_date')->nullable();
            $table->date('produced_at')->nullable();
            $table->string('source_type', 24)->default('manual')->comment('production, purchase, manual');
            $table->string('source_ref', 40)->nullable();
            $table->string('status', 16)->default('active')->comment('active, consumed, blocked, expired');
            $table->timestamps();

            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->unique(['material_id', 'lot_number']);
            $table->index(['material_id', 'status', 'expiry_date']);
        });

        // 37.2 Pengeluaran bahan (issue manual & backflush).
        Schema::create('mfg_material_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('production_order_id');
            $table->uuid('material_id');
            $table->uuid('lot_id')->nullable();
            $table->decimal('qty', 18, 6)->comment('Selalu positif — arah ditandai kind');
            $table->string('kind', 16)->default('issue')->comment('issue, backflush, return');
            $table->string('method', 8)->default('fifo')->comment('fifo, fefe, manual');
            $table->string('alert', 40)->nullable()->comment('shortage, no_lot');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('production_order_id')->references('id')->on('mfg_production_orders')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->index(['production_order_id', 'kind']);
        });

        // 37.3 Pelaporan operasi: mulai/selesai, qty baik/scrap/rework, durasi.
        Schema::create('mfg_operation_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('production_order_id');
            $table->uuid('routing_operation_id')->nullable();
            $table->uuid('work_center_id')->nullable();
            $table->unsignedBigInteger('worker_id')->nullable();
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->decimal('qty_good', 18, 6)->default(0);
            $table->decimal('qty_scrap', 18, 6)->default(0);
            $table->decimal('qty_rework', 18, 6)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('production_order_id')->references('id')->on('mfg_production_orders')->cascadeOnDelete();
            $table->foreign('work_center_id')->references('id')->on('mfg_work_centers')->nullOnDelete();
            $table->index(['production_order_id', 'sequence']);
        });

        // 37.4 Downtime & alasan kode standar (bahan OEE Fase 40).
        Schema::create('mfg_downtime_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('work_center_id');
            $table->uuid('production_order_id')->nullable();
            $table->string('reason_code', 24)->comment('machine_down, material_wait, setup, break, other');
            $table->text('detail')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('work_center_id')->references('id')->on('mfg_work_centers')->cascadeOnDelete();
            $table->index(['work_center_id', 'started_at']);
        });

        // 37.5 Penerimaan barang jadi: lot/serial + by-product.
        Schema::create('mfg_fg_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('production_order_id');
            $table->uuid('material_id');
            $table->decimal('qty', 18, 6);
            $table->json('serials')->nullable();
            $table->boolean('by_product')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('production_order_id')->references('id')->on('mfg_production_orders')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->index(['production_order_id', 'by_product']);
        });

        // 37.6 WIP: transfer antar operasi + snapshot per order/operasi.
        Schema::create('mfg_wip_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('production_order_id');
            $table->uuid('from_operation_id')->nullable();
            $table->uuid('to_operation_id')->nullable();
            $table->decimal('qty', 18, 6);
            $table->string('status', 16)->default('in_transit')->comment('in_transit, received');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('production_order_id')->references('id')->on('mfg_production_orders')->cascadeOnDelete();
            $table->index(['production_order_id', 'status']);
        });

        // 37.7 Rework & scrap: alasan, biaya, indikator melebihi toleransi → NCR.
        Schema::create('mfg_rework_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('production_order_id');
            $table->uuid('material_id')->nullable();
            $table->string('reason', 300);
            $table->decimal('qty', 18, 6);
            $table->string('kind', 8)->default('scrap')->comment('scrap, rework');
            $table->bigInteger('cost_idr')->default(0);
            $table->boolean('ncr_required')->default(false)->comment('Melebihi toleransi → NCR Fase 39');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('production_order_id')->references('id')->on('mfg_production_orders')->cascadeOnDelete();
            $table->index(['production_order_id', 'kind']);
        });

        // 37.8 Subkontrak (maklon proses): kirim bahan, terima olahan, biaya → PR jasa.
        Schema::create('mfg_subcontract_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('production_order_id');
            $table->uuid('supplier_id')->nullable()->comment('sup_suppliers.id (tautan string non-breaking)');
            $table->string('shipment_ref', 80)->nullable()->comment('lgx shipment (ShipmentBooking)');
            $table->decimal('qty_in', 18, 6)->default(0)->comment('Bahan terkirim');
            $table->decimal('qty_out', 18, 6)->default(0)->comment('Hasil olahan diterima');
            $table->bigInteger('service_cost_idr')->default(0);
            $table->string('pr_ref', 40)->nullable()->comment('PR jasa subkontrak (36.6 contract)');
            $table->string('status', 16)->default('shipping')->comment('shipping, received, settled');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('production_order_id')->references('id')->on('mfg_production_orders')->cascadeOnDelete();
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_subcontract_receipts');
        Schema::dropIfExists('mfg_rework_records');
        Schema::dropIfExists('mfg_wip_transfers');
        Schema::dropIfExists('mfg_fg_receipts');
        Schema::dropIfExists('mfg_downtime_logs');
        Schema::dropIfExists('mfg_operation_reports');
        Schema::dropIfExists('mfg_material_issues');
        Schema::dropIfExists('mfg_material_lots');
        Schema::dropIfExists('mfg_production_orders');
    }
};
