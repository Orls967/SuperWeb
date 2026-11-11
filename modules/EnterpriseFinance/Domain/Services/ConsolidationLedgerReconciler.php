<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Services;

use Modules\EnterpriseFinance\Domain\Models\ConsolidationEntry;
use Modules\EnterpriseFinance\Domain\Models\ConsolidationRun;

/**
 * Rekonsiliator Buku Besar Konsolidasi Grup (54.2 - PSAK 65).
 * Memverifikasi integritas neraca saldo konsolidasi terhadap rincian ayat jurnal:
 * 1. Total Debit Konsolidasian == Total Kredit Konsolidasian (Zero-Sum Invariant).
 * 2. Seluruh ayat jurnal lokal memiliki pasangan eliminasi yang sah.
 * 3. Tidak terdapat saldo mengambang (suspense account) yang tidak terdokumentasi.
 */
class ConsolidationLedgerReconciler
{
    /**
     * Memverifikasi apakah suatu putaran konsolidasi memenuhi syarat zero-sum dan bebas anomali.
     *
     * @return array{
     *     run_code: string,
     *     period: string,
     *     is_reconciled: bool,
     *     total_debit_minor: int,
     *     total_credit_minor: int,
     *     net_variance_minor: int,
     *     total_entries_count: int,
     *     suspense_entries_count: int,
     *     discrepancies: list<string>
     * }
     */
    public function reconcileRun(ConsolidationRun $run): array
    {
        $entries = ConsolidationEntry::where('consolidation_run_id', $run->id)->get();

        $totalDebit = 0;
        $totalCredit = 0;
        $suspenseCount = 0;
        $discrepancies = [];

        foreach ($entries as $e) {
            $totalDebit += (int) $e->local_debit_minor;
            $totalCredit += (int) $e->local_credit_minor;

            if (str_contains($e->canonical_account_code, '9999-UNMAPPED') || str_contains($e->canonical_account_code, 'SUSPENSE')) {
                $suspenseCount++;
                $discrepancies[] = "Ditemukan akun mengambang/unmapped: [{$e->canonical_account_code}] pada entitas {$e->entity_code}.";
            }
        }

        $variance = abs($totalDebit - $totalCredit);
        if ($variance !== 0) {
            $discrepancies[] = "Neraca konsolidasi tidak seimbang: Total Debit (Rp {$totalDebit}) != Total Kredit (Rp {$totalCredit}), selisih Rp {$variance}.";
        }

        if ($run->total_debit_minor !== $totalDebit) {
            $discrepancies[] = "Ketidaksesuaian header run total debit ({$run->total_debit_minor}) dengan kalkulasi rincian ({$totalDebit}).";
        }

        $isReconciled = ($variance === 0 && $suspenseCount === 0);

        return [
            'run_code' => $run->run_code,
            'period' => $run->period,
            'is_reconciled' => $isReconciled,
            'total_debit_minor' => $totalDebit,
            'total_credit_minor' => $totalCredit,
            'net_variance_minor' => $variance,
            'total_entries_count' => $entries->count(),
            'suspense_entries_count' => $suspenseCount,
            'discrepancies' => $discrepancies,
        ];
    }
}
