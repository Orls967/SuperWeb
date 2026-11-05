<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_universal_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_ref', 64)->unique();
            $table->string('order_reference', 64);
            $table->string('customer_account_ref', 64);
            $table->string('merchant_code', 64);
            $table->string('business_line_code', 16);
            $table->bigInteger('gross_amount_minor')->default(0);
            $table->bigInteger('admin_fee_minor')->default(0);
            $table->bigInteger('net_merchant_amount_minor')->default(0);
            $table->boolean('is_halal_verified')->default(true);
            $table->string('ledger_journal_ref', 64)->nullable();
            $table->string('status', 32)->default('SUCCESS');
            $table->string('rejection_reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_code', 'business_line_code'], 'syb_uwt_mch_line_idx');
            $table->index(['customer_account_ref', 'status'], 'syb_uwt_cust_status_idx');
            $table->index('order_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_universal_wallet_transactions');
    }
};
