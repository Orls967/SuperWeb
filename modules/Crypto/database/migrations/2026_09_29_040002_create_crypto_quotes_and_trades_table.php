<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->string('side'); // buy | sell
            $table->decimal('price_idr', 36, 18);
            $table->decimal('quantity', 36, 18);
            $table->decimal('gross_idr', 36, 18);
            $table->decimal('fee_idr', 36, 18);
            $table->timestamp('expires_at')->index();
            $table->boolean('is_executed')->default(false);
            $table->timestamps();
        });

        Schema::create('crypto_trades', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('crypto_quotes')->nullOnDelete();
            $table->string('side'); // buy | sell
            $table->decimal('quantity', 36, 18);
            $table->decimal('price_idr', 36, 18);
            $table->decimal('gross_idr', 36, 18);
            $table->decimal('fee_idr', 36, 18);
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->string('status')->default('completed');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_trades');
        Schema::dropIfExists('crypto_quotes');
    }
};
