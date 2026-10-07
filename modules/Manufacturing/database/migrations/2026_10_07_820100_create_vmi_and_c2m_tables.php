<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 82.1 VMI Triggers & Auto-PO
        Schema::create('mfg_vmi_replenishments', function (Blueprint $table) {
            $table->id();
            $table->string('vmi_code', 32)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->string('sku', 64);
            $table->integer('current_shelf_stock');
            $table->integer('reorder_point');
            $table->integer('suggested_reorder_qty');
            $table->bigInteger('total_estimated_idr');
            $table->bigInteger('contract_plafond_idr');
            $table->string('status', 32); // AUTO_ORDERED, PENDING_APPROVAL
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();
        });

        // 82.3 & 82.4 C2M Custom Configurations & Factory SPK Routing
        Schema::create('mfg_c2m_custom_orders', function (Blueprint $table) {
            $table->id();
            $table->string('c2m_code', 32)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('base_model', 64); // e.g. "EXHAUST_MOD_V8", "CUSTOM_SPOILER_CF"
            $table->json('parameters'); // size_mm, material, finish
            $table->decimal('complexity_factor', 4, 2)->default(1.0);
            $table->bigInteger('rolled_up_bom_cost_idr');
            $table->bigInteger('final_price_idr');
            $table->string('status', 32)->default('DESIGN_VALIDATED'); // DESIGN_VALIDATED, SPK_ISSUED, IN_PRODUCTION, QC_PASSED, SHIPPED
            $table->string('spk_number', 64)->nullable();
            $table->integer('estimated_lead_days')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_c2m_custom_orders');
        Schema::dropIfExists('mfg_vmi_replenishments');
    }
};
