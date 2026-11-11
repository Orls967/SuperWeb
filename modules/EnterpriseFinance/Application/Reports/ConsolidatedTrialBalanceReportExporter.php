<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Reports;

use Modules\EnterpriseFinance\Domain\Models\ConsolidationEntry;
use Modules\EnterpriseFinance\Domain\Models\ConsolidationRun;

/**
 * Exporter Laporan Neraca Saldo Konsolidasi Multi-Entitas (PSAK 65 / IAS 27).
 * Menyajikan saldo buku besar gabungan per entitas, jurnal eliminasi, dan saldo neto konsolidasian.
 */
final class ConsolidatedTrialBalanceReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $period): array
    {
        $run = ConsolidationRun::where('period', $period)->latest('id')->first();

        $rows = [];
        $rows[] = [
            'Period',
            'Entity Code',
            'Canonical Account Code',
            'Account Name',
            'Local Debit Minor',
            'Local Credit Minor',
            'Elimination Debit Minor',
            'Elimination Credit Minor',
            'Consolidated Net Minor',
            'Reporting Currency',
        ];

        $totalLocalDebit = 0;
        $totalLocalCredit = 0;
        $totalElimDebit = 0;
        $totalElimCredit = 0;
        $totalConsolidatedNet = 0;

        if ($run !== null) {
            $entries = ConsolidationEntry::where('consolidation_run_id', $run->id)->orderBy('canonical_account_code')->get();

            foreach ($entries as $e) {
                $totalLocalDebit += (int) $e->local_debit_minor;
                $totalLocalCredit += (int) $e->local_credit_minor;
                $totalElimDebit += (int) $e->elimination_debit_minor;
                $totalElimCredit += (int) $e->elimination_credit_minor;
                $totalConsolidatedNet += (int) $e->consolidated_net_minor;

                $rows[] = [
                    $run->period,
                    $e->entity_code,
                    $e->canonical_account_code,
                    $e->account_name,
                    (string) $e->local_debit_minor,
                    (string) $e->local_credit_minor,
                    (string) $e->elimination_debit_minor,
                    (string) $e->elimination_credit_minor,
                    (string) $e->consolidated_net_minor,
                    $run->reporting_currency ?? 'IDR',
                ];
            }
        }

        $rows[] = [
            'SUMMARY',
            $run ? "Run Code: {$run->run_code}" : 'No Run Executed',
            '',
            'TOTAL AGGREGATED',
            (string) $totalLocalDebit,
            (string) $totalLocalCredit,
            (string) $totalElimDebit,
            (string) $totalElimCredit,
            (string) $totalConsolidatedNet,
            ($totalLocalDebit === $totalLocalCredit) ? 'ZERO_SUM_BALANCED' : 'UNBALANCED',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "consolidated-trial-balance-{$period}.csv",
            'content' => $csv,
            'row_count' => count($rows) - 2,
            'sha256' => hash('sha256', $csv),
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function buildCsv(array $rows): string
    {
        $fp = fopen('php://memory', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);

        return $content ?: '';
    }
}
