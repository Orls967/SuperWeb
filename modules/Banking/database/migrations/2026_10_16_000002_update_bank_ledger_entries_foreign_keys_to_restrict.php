<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('bank_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropForeign(['account_id']);
            $table->foreign('transaction_id')->references('id')->on('bank_ledger_transactions')->restrictOnDelete();
            $table->foreign('account_id')->references('id')->on('bank_ledger_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('bank_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropForeign(['account_id']);
            $table->foreign('transaction_id')->references('id')->on('bank_ledger_transactions')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('bank_ledger_accounts')->cascadeOnDelete();
        });
    }
};
