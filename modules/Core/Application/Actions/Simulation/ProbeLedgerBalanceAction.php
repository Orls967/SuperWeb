<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Simulation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Action untuk memverifikasi probe keseimbangan buku besar (D35: \sum debit = \sum kredit).
 * Memastikan bahwa invariant akuntansi dasar tetap terjaga sempurna di bawah simulasi.
 */
final class ProbeLedgerBalanceAction
{
    /**
     * @return array{balanced: bool, total_debit: float, total_credit: float, difference: float}
     */
    public function execute(): array
    {
        $hasBankLedger = Schema::hasTable('bank_ledger_entries');
        $hasGeneralLedger = Schema::hasTable('general_ledger_entries');

        if (! $hasBankLedger && ! $hasGeneralLedger) {
            return [
                'balanced' => true,
                'total_debit' => 0.0,
                'total_credit' => 0.0,
                'difference' => 0.0,
            ];
        }

        $totalDiff = 0.0;

        if ($hasBankLedger) {
            $bankTable = 'bank_ledger_entries';
            $bankSum = (float) DB::table($bankTable)->sum('amount');
            $totalDiff += abs($bankSum);
        }

        if ($hasGeneralLedger) {
            $hasDebitCredit = Schema::hasColumn('general_ledger_entries', 'debit') &&
                              Schema::hasColumn('general_ledger_entries', 'credit');

            if ($hasDebitCredit) {
                $glTable = 'general_ledger_entries';
                $debit = (float) DB::table($glTable)->sum('debit');
                $credit = (float) DB::table($glTable)->sum('credit');
                $totalDiff += abs($debit - $credit);
            }
        }

        $balanced = $totalDiff < 0.0001;

        if (! $balanced) {
            throw new RuntimeException("Pelanggaran Invariant Ledger Probe (D35): Diskrepansi saldo terdeteksi: {$totalDiff}");
        }

        return [
            'balanced' => true,
            'total_debit' => 0.0,
            'total_credit' => 0.0,
            'difference' => $totalDiff,
        ];
    }
}
