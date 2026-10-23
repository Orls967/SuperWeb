<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('bank_ledger_accounts', function (Blueprint $table): void {
                $table->string('cached_balance', 64)->default('0')->change();
            });

            Schema::table('bank_ledger_entries', function (Blueprint $table): void {
                $table->string('amount', 64)->change();
                $table->string('balance_after', 64)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('bank_ledger_accounts', function (Blueprint $table): void {
                $table->decimal('cached_balance', 36, 18)->default(0)->change();
            });

            Schema::table('bank_ledger_entries', function (Blueprint $table): void {
                $table->decimal('amount', 36, 18)->change();
                $table->decimal('balance_after', 36, 18)->change();
            });
        }
    }
};
