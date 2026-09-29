<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique(); // e.g. 'wallet:user:42:IDR'
            $table->nullableMorphs('owner');
            $table->string('asset_code', 16);
            $table->string('kind', 32); // wallet, revenue, escrow, clearing, collateral, exchange, loan_receivable, fee
            $table->string('name');
            $table->boolean('allow_negative')->default(false);
            $table->decimal('cached_balance', 36, 18)->default(0);
            $table->boolean('is_frozen')->default(false);
            $table->timestamps();

            $table->index(['asset_code', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_ledger_accounts');
    }
};
