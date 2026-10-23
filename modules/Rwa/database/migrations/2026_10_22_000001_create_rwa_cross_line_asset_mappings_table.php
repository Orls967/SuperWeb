<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rwa_cross_line_asset_mappings')) {
            Schema::create('rwa_cross_line_asset_mappings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('rwa_asset_id')->constrained('rwa_assets')->cascadeOnDelete();
                $table->string('source_domain', 32);
                $table->string('source_entity_id', 64);
                $table->string('asset_type_code', 32);
                $table->unsignedInteger('annual_yield_target_bps')->default(800);
                $table->string('status', 24)->default('ACTIVE');
                $table->timestamps();

                $table->unique(['source_domain', 'source_entity_id'], 'rwa_src_domain_entity_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rwa_cross_line_asset_mappings');
    }
};
