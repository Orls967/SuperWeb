<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 39.1–39.8 QMS: rencana inspeksi, SPC, NCR/CAPA, penjualan per lot,
// recall, sertifikat, kalibrasi alat ukur.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_inspection_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->string('stage', 16)->default('in_process')->comment('receiving, in_process, final');
            $table->json('characteristics')->comment('nama, tipe atribut/variabel, satuan');
            $table->decimal('spec_min', 18, 6)->nullable()->comment('Batas bawah (variabel)');
            $table->decimal('spec_max', 18, 6)->nullable()->comment('Batas atas (variabel)');
            $table->decimal('aql_percent', 8, 4)->default(1.0)->comment('AQL simulasi');
            $table->unsignedSmallInteger('sample_size')->default(5);
            $table->string('frequency', 24)->default('per_batch')->comment('per_batch, per_shift, per_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['stage', 'is_active']);
        });

        Schema::create('mfg_gauges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->timestamp('calibrated_at')->nullable();
            $table->timestamp('calibration_due')->nullable();
            $table->string('certificate_ref')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['calibration_due', 'is_active']);
        });

        Schema::create('mfg_inspections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id')->nullable();
            $table->unsignedBigInteger('gauge_id')->nullable();
            $table->string('stage', 16)->comment('receiving, in_process, final');
            $table->string('subject_type', 40)->comment('grn, production_order, lot');
            $table->string('subject_id', 36);
            $table->uuid('lot_id')->nullable();
            $table->string('result', 16)->default('pending')->comment('pending, passed, failed, waived');
            $table->json('readings')->nullable()->comment('Nilai ukur sampel');
            $table->text('findings')->nullable();
            $table->foreignId('inspected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('mfg_inspection_plans')->nullOnDelete();
            $table->index(['stage', 'result']);
            $table->index(['subject_type', 'subject_id']);
        });

        // 39.3 SPC: subgroup X-bar/R per karakteristik.
        Schema::create('mfg_spc_samples', function (Blueprint $table) {
            $table->id();
            $table->uuid('plan_id');
            $table->uuid('inspection_id')->nullable();
            $table->unsignedSmallInteger('subgroup')->default(1);
            $table->decimal('mean_value', 18, 6);
            $table->decimal('range_value', 18, 6)->default(0);
            $table->decimal('sample_count', 8, 2)->default(5);
            $table->boolean('in_control')->default(true);
            $table->timestamp('taken_at');
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('mfg_inspection_plans')->cascadeOnDelete();
            $table->index(['plan_id', 'taken_at']);
        });

        // 39.4 NCR → investigasi → CAPA; tautan SCAR pemasok (query mentah).
        Schema::create('mfg_ncrs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique()->comment('NCR/{ENT}/YYYY-NNNNN (26.8)');
            $table->string('source', 24)->default('inspection')->comment('inspection, scrap, supplier');
            $table->uuid('inspection_id')->nullable();
            $table->uuid('production_order_id')->nullable();
            $table->string('supplier_id', 36)->nullable()->comment('sup_suppliers.id (tautan string)');
            $table->uuid('lot_id')->nullable();
            $table->string('severity', 16)->default('minor')->comment('minor, major, critical');
            $table->string('status', 24)->default('open')->comment('open, investigating, capa, closed');
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->string('scar_ref', 40)->nullable()->comment('SCAR pemasok Fase 32');
            $table->date('due_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['supplier_id']);
        });

        Schema::create('mfg_capas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ncr_id');
            $table->string('kind', 16)->default('corrective')->comment('corrective, preventive');
            $table->text('action');
            $table->date('due_date');
            $table->string('effectiveness', 16)->default('pending')->comment('pending, effective, ineffective');
            $table->string('status', 16)->default('open')->comment('open, done, verified, overdue');
            $table->text('verification_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('ncr_id')->references('id')->on('mfg_ncrs')->cascadeOnDelete();
            $table->index(['status', 'due_date']);
        });

        // Forward trace: lot FG terjual → item order Store (penerima akhir).
        Schema::create('mfg_lot_sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('lot_id');
            $table->unsignedBigInteger('store_order_item_id');
            $table->unsignedBigInteger('store_order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('qty', 18, 6);
            $table->bigInteger('cost_idr')->default(0);
            $table->timestamps();

            $table->foreign('lot_id')->references('id')->on('mfg_material_lots')->cascadeOnDelete();
            $table->index(['lot_id']);
            $table->index(['store_order_id']);
        });

        // 39.6 Recall per lot: daftar penerima + biaya + penghancuran.
        Schema::create('mfg_recalls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lot_id');
            $table->uuid('ncr_id')->nullable();
            $table->string('reason', 300);
            $table->string('status', 16)->default('planned')->comment('planned, notified, completed');
            $table->bigInteger('cost_idr')->default(0);
            $table->text('destruction_note')->nullable()->comment('Penghancuran bersertifikat');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('lot_id')->references('id')->on('mfg_material_lots')->cascadeOnDelete();
            $table->unique(['lot_id']);
        });

        Schema::create('mfg_recall_recipients', function (Blueprint $table) {
            $table->id();
            $table->uuid('recall_id');
            $table->unsignedBigInteger('lot_sale_id')->nullable();
            $table->unsignedBigInteger('store_order_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('contact_snapshot')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();

            $table->foreign('recall_id')->references('id')->on('mfg_recalls')->cascadeOnDelete();
            $table->index(['recall_id', 'notified_at']);
        });

        // 39.7 Sertifikat per lot (COA/COC/SNI/Halal/BPOM/GMP — simulasi).
        Schema::create('mfg_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lot_id');
            $table->string('type', 32)->comment('coa, coc, sni, halal, bpom, gmp');
            $table->string('number', 80);
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->string('issuer', 160)->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();

            $table->foreign('lot_id')->references('id')->on('mfg_material_lots')->cascadeOnDelete();
            $table->index(['lot_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_certificates');
        Schema::dropIfExists('mfg_recall_recipients');
        Schema::dropIfExists('mfg_recalls');
        Schema::dropIfExists('mfg_lot_sales');
        Schema::dropIfExists('mfg_capas');
        Schema::dropIfExists('mfg_ncrs');
        Schema::dropIfExists('mfg_spc_samples');
        Schema::dropIfExists('mfg_inspections');
        Schema::dropIfExists('mfg_gauges');
        Schema::dropIfExists('mfg_inspection_plans');
    }
};
