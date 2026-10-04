<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 36.7 Parameter perencanaan per material: stok pengaman, titik pesan ulang,
        // lead time, MOQ, dan aturan lot-sizing.
        Schema::create('mfg_planning_params', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_id')->unique();
            $table->decimal('safety_stock', 18, 6)->default(0);
            $table->decimal('reorder_point', 18, 6)->default(0);
            $table->unsignedInteger('lead_time_days')->default(7);
            $table->decimal('moq', 18, 6)->default(1);
            $table->string('lot_sizing', 16)->default('l4l')->comment('l4l, fixed, periodic, eoq');
            $table->decimal('fixed_order_qty', 18, 6)->nullable();
            $table->unsignedSmallInteger('period_weeks')->default(2);
            $table->bigInteger('ordering_cost_idr')->default(0);
            $table->bigInteger('holding_cost_per_unit_year_idr')->default(0);
            $table->timestamps();

            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
        });

        // 36.1 Forecast & demand: skenario ber-versi (urutan input, distributor, dll).
        Schema::create('mfg_forecast_scenarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 16)->default('draft')->comment('draft, active, archived');
            $table->string('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['name', 'version']);
        });

        Schema::create('mfg_forecast_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('scenario_id');
            $table->uuid('material_id');
            $table->date('period_start');
            $table->decimal('qty', 18, 6);
            $table->string('kind', 16)->default('forecast')->comment('order, forecast');
            $table->timestamps();

            $table->foreign('scenario_id')->references('id')->on('mfg_forecast_scenarios')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->unique(['scenario_id', 'material_id', 'period_start']);
            $table->index(['material_id', 'period_start']);
        });

        // 36.2 MPS: kuantitas barang jadi per periode + time fence (freeze).
        Schema::create('mfg_mps_headers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 16)->default('draft')->comment('draft, active, archived');
            $table->unsignedSmallInteger('freeze_days')->default(14)->comment('Time fence: N hari ke depan dibekukan');
            $table->date('horizon_end')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['name', 'version']);
        });

        Schema::create('mfg_mps_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('header_id');
            $table->uuid('material_id');
            $table->date('period_start');
            $table->decimal('qty', 18, 6);
            $table->boolean('frozen')->default(false)->comment('Baris dalam time fence');
            $table->timestamps();

            $table->foreign('header_id')->references('id')->on('mfg_mps_headers')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->unique(['header_id', 'material_id', 'period_start']);
        });

        // Posisi stok material pabrik (on-hand vs dipesan) — sumber netting MRP.
        Schema::create('mfg_material_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_id')->unique();
            $table->decimal('qty_on_hand', 18, 6)->default(0);
            $table->decimal('qty_reserved', 18, 6)->default(0);
            $table->timestamps();

            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
        });

        // 36.3 Scheduled receipts: PO/supply masuk yang akan datang (netting MRP).
        Schema::create('mfg_scheduled_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_id');
            $table->date('due_date');
            $table->decimal('qty', 18, 6);
            $table->string('source_type', 24)->default('manual')->comment('po, manual, production');
            $table->string('source_ref', 80)->nullable();
            $table->string('status', 16)->default('open')->comment('open, received, cancelled');
            $table->timestamps();

            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->index(['material_id', 'due_date', 'status']);
        });

        // 36.8 Run MRP: idempoten per run_key; perbandingan antar-run lewat summary.
        Schema::create('mfg_mrp_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('run_key', 64)->unique()->comment('sha256(params+input version)');
            $table->string('status', 16)->default('running')->comment('running, completed, failed, scenario');
            $table->boolean('is_scenario')->default(false)->comment('What-if: tidak menulis planned order nyata');
            $table->unsignedSmallInteger('horizon_days')->default(56);
            $table->unsignedSmallInteger('bucket_days')->default(7);
            $table->json('params')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });

        Schema::create('mfg_mrp_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id');
            $table->uuid('material_id');
            $table->date('period_start');
            $table->decimal('gross_req', 18, 6)->default(0);
            $table->decimal('scheduled_receipts', 18, 6)->default(0);
            $table->decimal('projected_on_hand', 18, 6)->default(0);
            $table->decimal('planned_order_qty', 18, 6)->default(0);
            $table->string('action', 24)->default('none')->comment('none, produce, purchase');

            $table->foreign('run_id')->references('id')->on('mfg_mrp_runs')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->index(['run_id', 'material_id', 'period_start']);
        });

        // 36.3/36.5 Order terencana → firm; usulan PR lewat contract Procurement.
        Schema::create('mfg_planned_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id')->nullable();
            $table->uuid('material_id');
            $table->string('kind', 16)->comment('production, purchase');
            $table->decimal('qty', 18, 6);
            $table->date('release_date')->comment('Mulai (due - lead time)');
            $table->date('due_date');
            $table->string('status', 16)->default('planned')->comment('planned, firm, converted, superseded, cancelled');
            $table->string('reservation_kind', 8)->nullable()->comment('hard, soft');
            $table->string('pr_ref', 40)->nullable()->comment('Nomor PR dari Procurement (36.6)');
            $table->unsignedBigInteger('production_order_id')->nullable()->comment('mfg_production_orders (Fase 37)');
            $table->string('superseded_by_run_id', 36)->nullable();
            $table->timestamps();

            $table->foreign('run_id')->references('id')->on('mfg_mrp_runs')->nullOnDelete();
            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->index(['status', 'due_date']);
            $table->index(['material_id', 'status']);
        });

        // 36.5 Reservasi bahan terhadap order firm (hard/soft).
        Schema::create('mfg_material_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('planned_order_id');
            $table->uuid('material_id');
            $table->decimal('qty', 18, 6);
            $table->string('kind', 8)->default('soft')->comment('hard, soft');
            $table->string('status', 16)->default('active')->comment('active, released, committed');
            $table->text('shortfall_note')->nullable();
            $table->timestamps();

            $table->foreign('planned_order_id')->references('id')->on('mfg_planned_orders')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->index(['material_id', 'status']);
        });

        // 36.4 CRP: beban per work center per periode vs kapasitas.
        Schema::create('mfg_capacity_loads', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id');
            $table->uuid('work_center_id');
            $table->date('period_start');
            $table->unsignedInteger('load_minutes')->default(0);
            $table->unsignedInteger('capacity_minutes')->default(0);
            $table->decimal('utilization_percent', 8, 2)->default(0);
            $table->boolean('bottleneck')->default(false);
            $table->timestamps();

            $table->foreign('run_id')->references('id')->on('mfg_mrp_runs')->cascadeOnDelete();
            $table->foreign('work_center_id')->references('id')->on('mfg_work_centers')->cascadeOnDelete();
            $table->unique(['run_id', 'work_center_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_capacity_loads');
        Schema::dropIfExists('mfg_material_reservations');
        Schema::dropIfExists('mfg_planned_orders');
        Schema::dropIfExists('mfg_mrp_requirements');
        Schema::dropIfExists('mfg_mrp_runs');
        Schema::dropIfExists('mfg_scheduled_receipts');
        Schema::dropIfExists('mfg_material_balances');
        Schema::dropIfExists('mfg_mps_lines');
        Schema::dropIfExists('mfg_mps_headers');
        Schema::dropIfExists('mfg_forecast_lines');
        Schema::dropIfExists('mfg_forecast_scenarios');
        Schema::dropIfExists('mfg_planning_params');
    }
};
