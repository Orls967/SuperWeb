<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('bank_ledger_transactions')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('bank_ledger_accounts')->cascadeOnDelete();
            $table->string('asset_code', 16);
            $table->decimal('amount', 36, 18);
            $table->decimal('balance_after', 36, 18);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'id']);
            $table->index('asset_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_ledger_entries');
    }
};
