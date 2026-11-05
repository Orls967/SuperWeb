<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_scf_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('facility_number', 64)->unique();
            $table->string('buyer_party_ref', 64)->index();
            $table->string('supplier_party_ref', 64)->index();
            $table->unsignedBigInteger('limit_amount_cents');
            $table->unsignedBigInteger('utilized_amount_cents')->default(0);
            $table->unsignedInteger('margin_rate_bps');
            $table->unsignedInteger('max_tenor_days');
            $table->string('status', 32)->index();
            $table->timestamps();
        });

        Schema::create('syb_scf_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('syb_scf_facilities')->cascadeOnDelete();
            $table->string('invoice_number', 64)->unique();
            $table->string('supplier_invoice_ref', 64);
            $table->unsignedBigInteger('principal_cost_cents');
            $table->unsignedBigInteger('margin_profit_cents');
            $table->unsignedBigInteger('total_sale_price_cents');
            $table->unsignedBigInteger('custody_document_id')->nullable()->index();
            $table->timestamp('qabdh_verified_at')->nullable();
            $table->date('due_date');
            $table->string('status', 32)->index();
            $table->timestamps();
        });

        Schema::create('syb_scf_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('syb_scf_invoices')->cascadeOnDelete();
            $table->string('repayment_reference', 64)->unique();
            $table->unsignedBigInteger('amount_paid_cents');
            $table->unsignedBigInteger('principal_portion_cents');
            $table->unsignedBigInteger('margin_portion_cents');
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_scf_repayments');
        Schema::dropIfExists('syb_scf_invoices');
        Schema::dropIfExists('syb_scf_facilities');
    }
};
