<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 32); // topup, transfer, fee, payment, manual_adjustment, exchange, etc.
            $table->nullableMorphs('reference');
            $table->string('idempotency_key')->unique();
            $table->string('description');
            $table->json('meta')->nullable();
            $table->timestamp('posted_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_ledger_transactions');
    }
};
