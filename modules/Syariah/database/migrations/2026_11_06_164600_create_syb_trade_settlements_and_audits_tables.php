<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_trade_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number', 64)->unique();
            $table->string('trade_contract_type', 32)->index();
            $table->string('contract_reference', 64)->index();
            $table->string('payer_party_ref', 64)->index();
            $table->string('payee_party_ref', 64)->index();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('IDR');
            $table->unsignedBigInteger('ujrah_fee_cents')->default(0);
            $table->string('settlement_channel', 32);
            $table->timestamp('settled_at');
            $table->timestamps();
        });

        Schema::create('syb_trade_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('audit_ref', 64)->index();
            $table->string('event_name', 64);
            $table->text('details')->nullable();
            $table->string('shariah_compliance_status', 32);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_trade_audit_logs');
        Schema::dropIfExists('syb_trade_settlements');
    }
};
