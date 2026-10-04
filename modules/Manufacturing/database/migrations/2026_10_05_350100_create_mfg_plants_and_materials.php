<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 35.1 Plant — pabrik per entitas hukum, area/line, kalender & kapasitas nominal.
        Schema::create('mfg_plants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 180);
            $table->uuid('legal_entity_id')->nullable()->comment('pty_legal_entities (tautan non-breaking)');
            $table->string('type', 32)->default('factory')->comment('factory, central_kitchen, workshop');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->unsignedInteger('nominal_capacity_per_day')->default(0);
            $table->string('capacity_uom', 32)->default('unit');
            $table->boolean('is_active')->default(true);
            $table->json('address')->nullable();
            $table->timestamps();

            $table->index(['legal_entity_id', 'is_active']);
            $table->index(['type', 'is_active']);
        });

        Schema::create('mfg_plant_areas', function (Blueprint $table) {
            $table->id();
            $table->uuid('plant_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('kind', 32)->default('area')->comment('area, line, warehouse, quality_lab');
            $table->unsignedInteger('nominal_capacity_per_day')->default(0);
            $table->string('capacity_uom', 32)->default('unit');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('plant_id')->references('id')->on('mfg_plants')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('mfg_plant_areas')->nullOnDelete();
            $table->unique(['plant_id', 'code']);
        });

        // Kalender produksi & shift: hari libur dan lembur bersifat simulasi (35.1).
        Schema::create('mfg_working_calendars', function (Blueprint $table) {
            $table->id();
            $table->uuid('plant_id');
            $table->date('calendar_date');
            $table->boolean('is_working_day')->default(true);
            $table->string('reason')->nullable();
            $table->boolean('overtime_allowed')->default(false);
            $table->timestamps();

            $table->foreign('plant_id')->references('id')->on('mfg_plants')->cascadeOnDelete();
            $table->unique(['plant_id', 'calendar_date']);
        });

        Schema::create('mfg_shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('plant_id');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('capacity_units')->default(0);
            $table->string('capacity_uom', 32)->default('unit');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('plant_id')->references('id')->on('mfg_plants')->cascadeOnDelete();
            $table->foreign('area_id')->references('id')->on('mfg_plant_areas')->nullOnDelete();
            $table->unique(['plant_id', 'code']);
        });

        // 35.2 Work center / mesin: kapasitas/jam, efisiensi, biaya per jam.
        Schema::create('mfg_work_centers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plant_id');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->uuid('asset_id')->nullable()->comment('ast_assets.id — tautan non-breaking (Fase 30)');
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('kind', 32)->default('machine')->comment('machine, labor_cell, line');
            $table->unsignedSmallInteger('capacity_per_hour')->default(1);
            $table->string('capacity_uom', 32)->default('unit');
            $table->unsignedTinyInteger('efficiency_percent')->default(100);
            $table->bigInteger('machine_cost_per_hour_idr')->default(0);
            $table->bigInteger('labor_cost_per_hour_idr')->default(0);
            $table->bigInteger('overhead_per_hour_idr')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('plant_id')->references('id')->on('mfg_plants')->cascadeOnDelete();
            $table->foreign('area_id')->references('id')->on('mfg_plant_areas')->nullOnDelete();
            $table->unique(['plant_id', 'code']);
            $table->index(['asset_id', 'is_active']);
        });

        // 35.3 Master material: raw, WIP, FG, packaging, by/co-product.
        Schema::create('mfg_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name', 200);
            $table->string('kind', 24)->default('raw')->comment('raw, wip, finished, packaging, by_product, co_product');
            $table->string('base_uom', 20)->default('pcs');
            $table->boolean('lot_tracked')->default(true);
            $table->boolean('expiry_tracked')->default(false);
            $table->boolean('serial_tracked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['kind', 'is_active']);
        });

        Schema::create('mfg_uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->uuid('material_id');
            $table->string('from_uom', 20);
            $table->string('to_uom', 20);
            $table->decimal('factor', 24, 8)->comment('1 from_uom = factor * to_uom');
            $table->timestamps();

            $table->foreign('material_id')->references('id')->on('mfg_materials')->cascadeOnDelete();
            $table->unique(['material_id', 'from_uom', 'to_uom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_uom_conversions');
        Schema::dropIfExists('mfg_materials');
        Schema::dropIfExists('mfg_work_centers');
        Schema::dropIfExists('mfg_shifts');
        Schema::dropIfExists('mfg_working_calendars');
        Schema::dropIfExists('mfg_plant_areas');
        Schema::dropIfExists('mfg_plants');
    }
};
