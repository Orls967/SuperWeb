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
            $hasAccountCode = Schema::hasColumn('bank_ledger_entries', 'account_code');
            $hasDebit = Schema::hasColumn('bank_ledger_entries', 'debit_amount');
            $hasCredit = Schema::hasColumn('bank_ledger_entries', 'credit_amount');

            if ($hasAccountCode || $hasDebit || $hasCredit) {
                Schema::table('bank_ledger_entries', function (Blueprint $table) use ($hasAccountCode, $hasDebit, $hasCredit) {
                    if ($hasAccountCode) {
                        try {
                            $table->dropIndex(['account_code']);
                        } catch (Throwable) {
                            // index does not exist or already dropped
                        }
                    }

                    $columnsToDrop = [];
                    if ($hasAccountCode) {
                        $columnsToDrop[] = 'account_code';
                    }
                    if ($hasDebit) {
                        $columnsToDrop[] = 'debit_amount';
                    }
                    if ($hasCredit) {
                        $columnsToDrop[] = 'credit_amount';
                    }

                    if (! empty($columnsToDrop)) {
                        $table->dropColumn($columnsToDrop);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // K-18: Kolom turunan tidak dikembalikan karena melanggar PR4
    }
};
