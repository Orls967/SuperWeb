<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 30.1 Kategori aset (PSAK 16 simulasi): umur ekonomis & metode default
        Schema::create('ast_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->unsignedSmallInteger('useful_life_years')->default(5);
            $table->string('depreciation_method', 24)->default('straight_line')->comment('straight_line, declining_balance, units_of_production');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // 30.2 Hirarki lokasi: entitas → site → area (self-referencing)
        Schema::create('ast_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('legal_entity_id')->nullable()->comment('Tautan non-breaking ke pty_legal_entities');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->string('level', 16)->default('site')->comment('entity, site, area');
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('ast_locations')->nullOnDelete();
            $table->index(['parent_id', 'level']);
        });

        // 30.2 Register aset utama
        Schema::create('ast_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('asset_number', 40)->unique()->comment('Nomor via DocumentNumbering (26.8): AST/{entity}/YYYY-NNNNN');
            $table->string('asset_tag', 60)->unique()->comment('Tag/QR fisik');
            $table->string('name', 200);
            $table->string('description')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->uuid('legal_entity_id')->nullable()->comment('Entitas pemilik');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Penanggung jawab');
            $table->string('condition', 24)->default('good')->comment('good, fair, poor, broken');
            $table->string('status', 24)->default('in_use')->comment('in_use, idle, under_maintenance, disposed');
            $table->string('brand')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('acquired_at')->nullable();
            $table->date('in_service_at')->nullable();
            $table->bigInteger('acquisition_cost_idr')->default(0);
            $table->bigInteger('landed_cost_idr')->default(0)->comment('Biaya perolehan termasuk landed cost');
            $table->bigInteger('accumulated_depreciation_idr')->default(0);
            $table->bigInteger('book_value_idr')->default(0);
            $table->string('source_type', 32)->nullable()->comment('purchase, direct, cip');
            $table->unsignedBigInteger('source_id')->nullable()->comment('PO/GRN/konstruksi sumber');
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('ast_categories');
            $table->foreign('location_id')->references('id')->on('ast_locations')->nullOnDelete();
            $table->index(['status', 'condition']);
            $table->index(['category_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        // 30.4 Riwayat aset hash-chain append-only
        Schema::create('ast_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('asset_id');
            $table->unsignedInteger('sequence');
            $table->string('event_type', 40)->comment('acquisition, move, repair, revaluation, disposal, insurance, assignment, stocktake');
            $table->json('payload');
            $table->string('prev_hash', 64);
            $table->string('hash', 64);
            $table->string('created_by_name')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('ast_assets')->cascadeOnDelete();
            $table->unique(['asset_id', 'sequence']);
        });

        // 30.7 Stok opname aset (scan QR)
        Schema::create('ast_stocktakes', function (Blueprint $table) {
            $table->id();
            $table->uuid('asset_id');
            $table->unsignedBigInteger('cycle_id')->comment('Batch opname');
            $table->string('result', 16)->comment('found, missing, unexpected');
            $table->foreignId('scanned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('approval_id')->nullable()->comment('Penyesuaian butuh approval (26.9)');
            $table->string('adjustment_status', 16)->default('none')->comment('none, pending, approved, rejected');
            $table->bigInteger('adjustment_amount_idr')->default(0);
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('ast_assets')->cascadeOnDelete();
            $table->unique(['asset_id', 'cycle_id']);
            $table->index('cycle_id');
        });

        // 30.8 Penugasan & peminjaman aset (check-out/in)
        Schema::create('ast_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('asset_id');
            $table->foreignId('assigned_to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at');
            $table->timestamp('checked_in_at')->nullable();
            $table->string('purpose')->nullable();
            $table->text('condition_out')->nullable();
            $table->text('condition_in')->nullable();
            $table->string('status', 16)->default('out')->comment('out, returned');
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('ast_assets')->cascadeOnDelete();
            $table->index(['status', 'checked_out_at']);
        });

        // 30.8 Asuransi aset: polis, jatuh tempo, klaim
        Schema::create('ast_insurances', function (Blueprint $table) {
            $table->id();
            $table->uuid('asset_id');
            $table->string('policy_number', 60)->unique();
            $table->string('provider', 160);
            $table->bigInteger('coverage_amount_idr')->default(0);
            $table->bigInteger('annual_premium_idr')->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('active')->comment('active, expired, claim_filed, claim_settled');
            $table->bigInteger('claim_amount_idr')->default(0);
            $table->text('claim_notes')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('ast_assets')->cascadeOnDelete();
            $table->index(['status', 'end_date']);
        });

        // 30.6 Tautan non-breaking dari data lama → aset (kolom nullable, backfill idempoten)
        $legacyTables = [
            'mall_assets',
            'lgx_trucks',
            'lgx_trailers',
            'lgx_vessels',
            'lgx_aircraft',
            'lgx_containers',
        ];

        foreach ($legacyTables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'asset_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->uuid('asset_id')->nullable()->after('id')->index();
                });
            }
        }

        // Peralatan dapur Resto: tabel peralatan/eq dikenali lewat ingredient/equipment bila ada
        foreach (['resto_kitchen_equipments', 'resto_equipment'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'asset_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->uuid('asset_id')->nullable()->after('id')->index();
                });
            }
        }
    }

    public function down(): void
    {
        $legacyTables = [
            'mall_assets', 'lgx_trucks', 'lgx_trailers', 'lgx_vessels',
            'lgx_aircraft', 'lgx_containers', 'resto_kitchen_equipments', 'resto_equipment',
        ];

        foreach ($legacyTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'asset_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('asset_id');
                });
            }
        }

        Schema::dropIfExists('ast_insurances');
        Schema::dropIfExists('ast_assignments');
        Schema::dropIfExists('ast_stocktakes');
        Schema::dropIfExists('ast_events');
        Schema::dropIfExists('ast_assets');
        Schema::dropIfExists('ast_locations');
        Schema::dropIfExists('ast_categories');
    }
};
