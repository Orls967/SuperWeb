<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payer_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('payable');
            $table->decimal('amount', 36, 18);
            $table->string('currency', 16)->default('IDR');
            $table->string('status', 32)->default('pending');
            $table->foreignId('hold_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->foreignId('capture_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->foreignId('release_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->string('idempotency_key')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_payment_intents');
    }
};
