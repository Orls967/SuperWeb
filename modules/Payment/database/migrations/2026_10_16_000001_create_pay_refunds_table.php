<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pay_refunds')) {
            Schema::create('pay_refunds', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('payment_intent_id')->constrained('pay_payment_intents')->cascadeOnDelete();
                $table->string('amount');
                $table->string('currency', 16)->default('IDR');
                $table->text('reason')->nullable();
                $table->string('status', 32)->default('succeeded');
                $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
                $table->timestamps();

                $table->index('payment_intent_id');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_refunds');
    }
};
