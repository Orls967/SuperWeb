<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_salam_trade_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 64)->unique();
            $table->string('seller_party_ref', 64)->index();
            $table->string('buyer_party_ref', 64)->index();
            $table->string('commodity_code', 32)->index();
            $table->string('commodity_grade', 32);
            $table->string('quantity_unit', 16)->default('MT');
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_delivered')->default(0);
            $table->unsignedBigInteger('advance_payment_cents');
            $table->date('promised_delivery_date');
            $table->string('delivery_warehouse_code', 64);
            $table->string('status', 32)->index();
            $table->timestamps();
        });

        Schema::create('syb_parallel_salam_hedges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salam_id')->constrained('syb_salam_trade_contracts')->cascadeOnDelete();
            $table->string('hedge_contract_number', 64)->unique();
            $table->string('offtaker_party_ref', 64)->index();
            $table->unsignedBigInteger('agreed_sale_price_cents');
            $table->unsignedInteger('quantity_hedged');
            $table->date('delivery_date');
            $table->string('status', 32)->index();
            $table->timestamps();
        });

        Schema::create('syb_salam_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salam_id')->constrained('syb_salam_trade_contracts')->cascadeOnDelete();
            $table->string('delivery_batch_ref', 64)->unique();
            $table->unsignedInteger('quantity_received');
            $table->string('warehouse_receipt_number', 64)->nullable();
            $table->string('inspection_status', 32);
            $table->timestamp('delivered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_salam_deliveries');
        Schema::dropIfExists('syb_parallel_salam_hedges');
        Schema::dropIfExists('syb_salam_trade_contracts');
    }
};
