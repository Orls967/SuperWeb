<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconcile_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('last_reconciled_entry_id')->index();
            $table->json('account_balances');
            $table->json('asset_balances');
            $table->string('transaction_checksum', 64);
            $table->timestamp('reconciled_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconcile_checkpoints');
    }
};
