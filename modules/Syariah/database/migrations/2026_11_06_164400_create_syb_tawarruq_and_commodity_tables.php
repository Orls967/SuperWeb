<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_commodity_lots', function (Blueprint $table) {
            $table->id();
            $table->string('lot_number', 64)->unique();
            $table->string('exchange_name', 64);
            $table->string('commodity_type', 32)->index();
            $table->string('physical_serial_number', 64)->unique();
            $table->string('warehouse_location', 64);
            $table->unsignedInteger('unit_quantity');
            $table->unsignedBigInteger('acquisition_cost_cents');
            $table->string('lot_status', 32)->index();
            $table->string('assigned_order_ref', 64)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('syb_tawarruq_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commodity_lot_id')->constrained('syb_commodity_lots')->cascadeOnDelete();
            $table->string('order_number', 64)->unique();
            $table->string('customer_party_ref', 64)->index();
            $table->string('source_currency', 3)->default('IDR');
            $table->unsignedBigInteger('source_amount_cents');
            $table->string('target_currency', 3)->default('USD');
            $table->unsignedBigInteger('target_amount_cents');
            $table->unsignedInteger('spot_rate_bps');
            $table->unsignedInteger('tenor_days');
            $table->unsignedInteger('markup_rate_bps');
            $table->unsignedBigInteger('markup_profit_cents');
            $table->unsignedBigInteger('broker_fee_cents')->default(0);
            $table->unsignedBigInteger('total_deferred_price_cents');
            $table->unsignedInteger('effective_rate_bps');
            $table->string('certificate_hash', 64);
            $table->string('status', 32)->index();
            $table->timestamps();
        });

        Schema::create('syb_tawarruq_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('syb_tawarruq_orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('leg_number');
            $table->string('transaction_reference', 64)->unique();
            $table->string('counterparty_name', 128);
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->timestamp('executed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_tawarruq_transactions');
        Schema::dropIfExists('syb_tawarruq_orders');
        Schema::dropIfExists('syb_commodity_lots');
    }
};
