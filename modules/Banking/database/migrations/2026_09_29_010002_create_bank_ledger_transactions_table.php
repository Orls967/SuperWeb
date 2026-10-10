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
            $table->string('reference_type', 160)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('description');
            $table->json('meta')->nullable();
            $table->timestamp('posted_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'posted_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_ledger_transactions');
    }
};
