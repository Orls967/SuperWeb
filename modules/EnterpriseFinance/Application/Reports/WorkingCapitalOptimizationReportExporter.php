<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Reports;

use Modules\EnterpriseFinance\Domain\Models\WorkingCapitalMetric;

/**
 * Exporter Laporan Optimalisasi Modal Kerja & Cash Conversion Cycle (54.5).
 * Memuat rasio efisiensi DSO (Days Sales Outstanding), DIO (Days Inventory Outstanding),
 * DPO (Days Payables Outstanding), CCC (Cash Conversion Cycle), serta estimasi pembebasan kas likuid.
 */
final class WorkingCapitalOptimizationReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $period): array
    {
        $metrics = WorkingCapitalMetric::where('period', $period)
            ->orderBy('entity_code')
            ->get();

        $rows = [];
        $rows[] = [
            'Period',
            'Entity Code',
            'Revenue Minor',
            'COGS Minor',
            'AR Balance Minor',
            'Inventory Balance Minor',
            'AP Balance Minor',
            'Net Working Capital Minor',
            'DSO (Days)',
            'DIO (Days)',
            'DPO (Days)',
            'CCC (Days)',
            'Efficiency Tier',
            'Potential Cash Release IDR',
        ];

        $totalNwc = 0;
        $totalCashRelease = 0;

        foreach ($metrics as $m) {
            $nwc = $m->netWorkingCapitalMinor();
            $totalNwc += $nwc;

            // Potensi kas dilepaskan jika CCC dipersingkat 5 hari
            // (Revenue / 365) * 5
            $dailySales = ($m->revenue_minor > 0) ? (int) intdiv($m->revenue_minor, 365) : 0;
            $cashRelease = $dailySales * 5;
            $totalCashRelease += $cashRelease;

            $rows[] = [
                $m->period,
                $m->entity_code,
                (string) $m->revenue_minor,
                (string) $m->cogs_minor,
                (string) $m->ar_balance_minor,
                (string) $m->inventory_balance_minor,
                (string) $m->ap_balance_minor,
                (string) $nwc,
                (string) $m->dso_days,
                (string) $m->dio_days,
                (string) $m->dpo_days,
                (string) $m->ccc_days,
                $m->efficiency_tier,
                (string) $cashRelease,
            ];
        }

        $rows[] = [
            'SUMMARY',
            "Entities Analyzed: {$metrics->count()}",
            '',
            '',
            '',
            '',
            'Total Group NWC',
            (string) $totalNwc,
            'Benchmark DSO: 45',
            'Benchmark DIO: 30',
            'Benchmark DPO: 60',
            'Target CCC: 15',
            'Potential Cash Unlock',
            (string) $totalCashRelease,
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "working-capital-optimization-{$period}.csv",
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
