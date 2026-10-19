<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 82.1 VMI Vendor Agreements & Contract Plafond
        Schema::create('prc_vmi_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('agreement_code', 32)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->string('sku', 64);
            $table->integer('reorder_point');
            $table->integer('reorder_qty');
            $table->bigInteger('unit_cost_idr');
            $table->bigInteger('contract_plafond_idr');
            $table->string('status', 32)->default('ACTIVE');
            $table->dateTime('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'sku'], 'prc_vmi_supp_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prc_vmi_agreements');
    }
};
