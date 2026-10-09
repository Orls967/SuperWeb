<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 38.1–38.6 Costing: versi biaya, standard cost, actual per order, varians.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_cost_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 16)->default('draft')->comment('draft, pending_approval, approved, retired');
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version']);
            $table->index('status');
        });

        Schema::create('mfg_standard_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('version_id');
            $table->uuid('material_id');
            $table->bigInteger('material_cost_idr')->default(0)->comment('Σ bahan × std cost komponen');
            $table->bigInteger('conversion_cost_idr')->default(0)->comment('Routing menit × biaya/jam WC');
            $table->bigInteger('overhead_cost_idr')->default(0);
            $table->bigInteger('unit_cost_idr')->default(0)->comment('Total std per unit output');
            $table->timestamps();

            $table->foreign('version_id')->references('id')->on('mfg_cost_versions')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->unique(['version_id', 'material_id']);
        });

        // 38.2 Actual costing per order (di-snapshot saat posting).
        Schema::create('mfg_order_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->unique();
            $table->bigInteger('material_idr')->default(0);
            $table->bigInteger('labor_idr')->default(0);
            $table->bigInteger('machine_idr')->default(0);
            $table->bigInteger('overhead_idr')->default(0);
            $table->bigInteger('subcontract_idr')->default(0);
            $table->bigInteger('byproduct_credit_idr')->default(0)->comment('Nilai by-product (38.6)');
            $table->bigInteger('total_idr')->default(0);
            $table->bigInteger('unit_cost_idr')->default(0)->comment('total / qty_completed');
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('mfg_production_orders')->cascadeOnDelete();
        });

        // 38.4 Varians vs standard; policy post → jurnal, capitalize → biaya jadi.
        Schema::create('mfg_variances', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->string('kind', 32)->comment('price, usage, labor_efficiency, overhead_volume, yield');
            $table->bigInteger('amount_idr')->default(0)->comment('Positif = unfavourable (bebani), negatif = favourable');
            $table->string('policy', 16)->default('post')->comment('post, capitalize');
            $table->boolean('posted')->default(false);
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('mfg_production_orders')->cascadeOnDelete();
            $table->unique(['order_id', 'kind']);
            $table->index(['kind', 'posted']);
        });

        // Biaya per lot (FIFO) untuk actual material & COGS.
        Schema::table('mfg_material_lots', function (Blueprint $table) {
            $table->bigInteger('unit_cost_idr')->default(0)->after('qty');
        });

        // Harga pokok terpasang per penerimaan FG (audit 38.8).
        Schema::table('mfg_fg_receipts', function (Blueprint $table) {
            $table->bigInteger('unit_cost_idr')->default(0)->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('mfg_fg_receipts', function (Blueprint $table) {
            $table->dropColumn('unit_cost_idr');
        });
        Schema::table('mfg_material_lots', function (Blueprint $table) {
            $table->dropColumn('unit_cost_idr');
        });
        Schema::dropIfExists('mfg_variances');
        Schema::dropIfExists('mfg_order_costs');
        Schema::dropIfExists('mfg_standard_costs');
        Schema::dropIfExists('mfg_cost_versions');
    }
};
