<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_writeoff_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('financing_ref', 64)->unique();
            $table->string('customer_ref', 64);
            $table->bigInteger('written_off_principal_minor')->default(0);
            $table->bigInteger('written_off_margin_minor')->default(0);
            $table->bigInteger('total_writeoff_minor')->default(0);
            $table->string('dps_approval_ref', 64)->nullable();
            $table->string('board_approval_ref', 64)->nullable();
            $table->string('status', 32)->default('REQUESTED');
            $table->text('writeoff_reason');
            $table->string('certificate_hash', 64);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_ref', 'status']);
        });

        Schema::create('syb_collateral_auctions', function (Blueprint $table) {
            $table->id();
            $table->string('auction_ref', 64)->unique();
            $table->string('financing_ref', 64);
            $table->string('collateral_description', 255);
            $table->bigInteger('appraised_value_minor')->default(0);
            $table->bigInteger('reserve_price_minor')->default(0);
            $table->bigInteger('winning_bid_minor')->default(0);
            $table->string('buyer_ref', 64)->nullable();
            $table->date('auction_date');
            $table->bigInteger('surplus_refunded_to_customer_minor')->default(0);
            $table->string('status', 32)->default('SCHEDULED');
            $table->timestamps();

            $table->index(['financing_ref', 'status']);
        });

        Schema::create('syb_recovery_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_ref', 64)->unique();
            $table->string('financing_ref', 64);
            $table->string('recovery_source', 32);
            $table->bigInteger('amount_minor')->default(0);
            $table->bigInteger('principal_recovery_minor')->default(0);
            $table->bigInteger('margin_recovery_minor')->default(0);
            $table->bigInteger('allocated_to_charity_minor')->default(0);
            $table->string('ledger_journal_ref', 64)->nullable();
            $table->timestamps();

            $table->index(['financing_ref', 'recovery_source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_recovery_receipts');
        Schema::dropIfExists('syb_collateral_auctions');
        Schema::dropIfExists('syb_writeoff_approvals');
    }
};
