<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 35.4 BOM multi-level ber-versi, effective window, alternative, scrap, by/co-product.
        Schema::create('mfg_boms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('output_material_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('name', 180);
            $table->decimal('output_qty', 18, 6)->default(1);
            $table->string('output_uom', 20)->default('pcs');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('change_reason')->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamps();

            $table->foreign('output_material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->unique(['output_material_id', 'version']);
            $table->index(['output_material_id', 'is_active', 'effective_from', 'effective_to']);
        });

        Schema::create('mfg_bom_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('bom_id');
            $table->uuid('input_material_id');
            $table->decimal('qty', 18, 6);
            $table->string('uom', 20);
            $table->decimal('scrap_percent', 8, 4)->default(0);
            $table->boolean('is_alternative')->default(false);
            $table->string('substitution_group', 40)->nullable();
            $table->boolean('is_by_product')->default(false);
            $table->boolean('is_co_product')->default(false);
            $table->decimal('allocation_percent', 8, 4)->nullable()->comment('Alokasi biaya co-product, total 100');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->timestamps();

            $table->foreign('bom_id')->references('id')->on('mfg_boms')->cascadeOnDelete();
            $table->foreign('input_material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->index(['bom_id', 'sequence']);
        });

        // 35.5 Routing: urutan operasi/work center, waktu setup/run, instruksi, titik inspeksi.
        Schema::create('mfg_routings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('output_material_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('name', 180);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('change_reason')->nullable();
            $table->timestamps();

            $table->foreign('output_material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->unique(['output_material_id', 'version']);
        });

        Schema::create('mfg_routing_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('routing_id');
            $table->uuid('work_center_id')->nullable();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('name', 160);
            $table->unsignedInteger('setup_minutes')->default(0);
            $table->unsignedInteger('run_minutes_per_unit')->default(0);
            $table->text('work_instructions')->nullable();
            $table->boolean('inspection_point')->default(false);
            $table->timestamps();

            $table->foreign('routing_id')->references('id')->on('mfg_routings')->cascadeOnDelete();
            $table->foreign('work_center_id')->references('id')->on('mfg_work_centers')->nullOnDelete();
            $table->index(['routing_id', 'sequence']);
        });

        // 35.6 Formula/resep proses: yield, toleransi, bahan aktif; perubahan via approval + versi/hash.
        Schema::create('mfg_formulas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('output_material_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('name', 180);
            $table->decimal('standard_yield_percent', 8, 4)->default(100);
            $table->decimal('yield_tolerance_percent', 8, 4)->default(5);
            $table->json('active_ingredients')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('draft')->comment('draft, pending_approval, approved, retired');
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
            $table->string('change_reason')->nullable();
            $table->timestamps();

            $table->foreign('output_material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->unique(['output_material_id', 'version']);
            $table->index(['status', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_formulas');
        Schema::dropIfExists('mfg_routing_operations');
        Schema::dropIfExists('mfg_routings');
        Schema::dropIfExists('mfg_bom_lines');
        Schema::dropIfExists('mfg_boms');
    }
};
