<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 52.1 Relasi Kepemilikan Saham & Hirarki Konsolidasi Grup
        if (! Schema::hasTable('ic_ownership_links')) {
            Schema::create('ic_ownership_links', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('parent_entity_code', 32);
                $table->string('child_entity_code', 32);
                $table->integer('ownership_pct_bps')->default(10000); // 10000 = 100.00%
                $table->string('consolidation_method', 32)->default('FULL'); // FULL, EQUITY, PROPORTIONAL
                $table->date('effective_date');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['parent_entity_code', 'child_entity_code']);
            });
        }

        // 52.3 Mutasi & Pengalihan Aset Tetap Antar-Entitas
        if (! Schema::hasTable('ic_inter_entity_transfers')) {
            Schema::create('ic_inter_entity_transfers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('transfer_code', 32)->unique();
                $table->string('asset_tag', 64);
                $table->string('source_entity_code', 32);
                $table->string('target_entity_code', 32);
                $table->bigInteger('net_book_value_minor')->default(0);
                $table->bigInteger('accum_depr_minor')->default(0);
                $table->bigInteger('transfer_price_minor')->default(0);
                $table->bigInteger('gain_loss_minor')->default(0);
                $table->string('status', 32)->default('PENDING'); // PENDING, APPROVED, COMPLETED, REJECTED
                $table->string('authorized_by_ref', 64)->nullable();
                $table->timestamps();

                $table->index(['source_entity_code', 'target_entity_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ic_inter_entity_transfers');
        Schema::dropIfExists('ic_ownership_links');
    }
};
