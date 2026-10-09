<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 187.1 & 187.5: Unified payment transactions across 30 lines (with idempotency key)
        Schema::create('pay_unified_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_key')->unique(); // Idempotent key
            $table->string('line_code'); // L01 - L30
            $table->string('payment_method'); // WALLET, VA, QRIS, ESCROW
            $table->decimal('gross_amount_idr', 18, 2);
            $table->string('status')->default('SETTLED');
            $table->timestamps();
        });

        // 187.2: Daily intercompany netting clearing (clears AR vs AP and leaves net balance)
        Schema::create('pay_intercompany_nettings', function (Blueprint $table) {
            $table->id();
            $table->string('netting_batch_code')->unique();
            $table->string('entity_a');
            $table->string('entity_b');
            $table->decimal('gross_receivables_idr', 18, 2);
            $table->decimal('gross_payables_idr', 18, 2);
            $table->decimal('netted_settlement_idr', 18, 2);
            $table->string('settling_entity');
            $table->timestamps();
        });

        // 187.4: Treasury cash pooling sweep (verifies account balance cannot go negative)
        Schema::create('pay_treasury_cash_pools', function (Blueprint $table) {
            $table->id();
            $table->string('sweep_code')->unique();
            $table->string('source_account');
            $table->string('target_pool_account');
            $table->decimal('swept_amount_idr', 18, 2);
            $table->decimal('source_balance_after_idr', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_treasury_cash_pools');
        Schema::dropIfExists('pay_intercompany_nettings');
        Schema::dropIfExists('pay_unified_transactions');
    }
};
