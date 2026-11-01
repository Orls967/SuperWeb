<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_ledger_entries')) {
            Schema::table('bank_ledger_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('bank_ledger_entries', 'account_code')) {
                    $table->string('account_code', 128)->nullable()->index()->after('account_id');
                }
                if (! Schema::hasColumn('bank_ledger_entries', 'debit_amount')) {
                    $table->decimal('debit_amount', 36, 18)->default(0)->after('amount');
                }
                if (! Schema::hasColumn('bank_ledger_entries', 'credit_amount')) {
                    $table->decimal('credit_amount', 36, 18)->default(0)->after('debit_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bank_ledger_entries')) {
            Schema::table('bank_ledger_entries', function (Blueprint $table) {
                $table->dropColumn(['account_code', 'debit_amount', 'credit_amount']);
            });
        }
    }
};
