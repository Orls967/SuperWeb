<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_agri_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 64)->unique();
            $table->string('target_product_sku', 64);
            $table->unsignedBigInteger('planned_input_grams');
            $table->unsignedBigInteger('actual_output_grams')->default(0);
            $table->unsignedBigInteger('waste_grams')->default(0);
            $table->string('status', 32)->default('PLANNED');
            $table->integer('protein_bps')->default(0);
            $table->integer('moisture_bps')->default(0);
            $table->string('export_grade', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('mfg_agri_batch_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mfg_agri_batch_id')->constrained('mfg_agri_batches')->cascadeOnDelete();
            $table->foreignUuid('agri_collection_batch_id')->constrained('agri_collection_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('allocated_grams');
            $table->unsignedBigInteger('cost_idr');
            $table->timestamps();
        });

        Schema::create('mfg_agri_genealogy_edges', function (Blueprint $table) {
            $table->id();
            $table->string('parent_entity_type', 32);
            $table->string('parent_entity_id', 64);
            $table->string('child_entity_type', 32);
            $table->string('child_entity_id', 64);
            $table->unsignedBigInteger('quantity_grams')->default(0);
            $table->timestamps();

            $table->index(['parent_entity_type', 'parent_entity_id'], 'mfg_agri_parent_idx');
            $table->index(['child_entity_type', 'child_entity_id'], 'mfg_agri_child_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_agri_genealogy_edges');
        Schema::dropIfExists('mfg_agri_batch_inputs');
        Schema::dropIfExists('mfg_agri_batches');
    }
};
