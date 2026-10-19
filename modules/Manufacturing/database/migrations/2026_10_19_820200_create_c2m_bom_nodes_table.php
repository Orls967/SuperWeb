<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 82.3 & 82.4 C2M Custom BOM Nodes & Routing Breakdown
        Schema::create('mfg_c2m_bom_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('c2m_custom_order_id')->constrained('mfg_c2m_custom_orders')->cascadeOnDelete();
            $table->string('node_code', 64);
            $table->string('parent_node_code', 64)->nullable();
            $table->string('part_name', 128);
            $table->string('material', 64);
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_cost_idr');
            $table->bigInteger('subtotal_cost_idr');
            $table->timestamps();

            $table->index(['c2m_custom_order_id', 'node_code'], 'mfg_bom_order_node_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_c2m_bom_nodes');
    }
};
