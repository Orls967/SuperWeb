<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Reports;

use Modules\EnterpriseFinance\Domain\Models\EfSegmentFinancialReport;

/**
 * Exporter Laporan Ringkasan Eksekutif Keuangan Konsolidasi Korporat (Executive Financial Summary).
 * Merangkum 17 baris segment reporting keuangan holding dan entitas anak (PSAK 5 / IFRS 8),
 * margin profitabilitas operasional, struktur solvabilitas grup, dan utilisasi modal korporat.
 */
final class ExecutiveConsolidatedFinancialSummaryReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $period): array
    {
        $segments = EfSegmentFinancialReport::where('period', $period)
            ->orderBy('segment_name')
            ->get();

        $rows = [];
        $rows[] = [
            'Period',
            'Segment Name',
            'Revenue Minor',
            'COGS Minor',
            'Operating Expenses Minor',
            'EBITDA Minor',
            'Capex Minor',
            'Assets Minor',
            'Liabilities Minor',
            'Net Equity Minor',
            'Operating Margin BPS',
        ];

        $totalRev = 0;
        $totalCogs = 0;
        $totalOpex = 0;
        $totalEbitda = 0;
        $totalCapex = 0;
        $totalAssets = 0;
        $totalLiab = 0;

        foreach ($segments as $s) {
            $rev = (int) $s->revenue_minor;
            $cogs = (int) $s->cogs_minor;
            $opex = (int) $s->operating_expenses_minor;
            $ebitda = (int) $s->ebitda_minor;
            $capex = (int) $s->capex_minor;
            $assets = (int) $s->assets_minor;
            $liab = (int) $s->liabilities_minor;
            $equity = $assets - $liab;

            $marginBps = ($rev > 0) ? (int) intdiv($ebitda * 10000, $rev) : 0;

            $totalRev += $rev;
            $totalCogs += $cogs;
            $totalOpex += $opex;
            $totalEbitda += $ebitda;
            $totalCapex += $capex;
            $totalAssets += $assets;
            $totalLiab += $liab;

            $rows[] = [
                $s->period,
                $s->segment_name,
                (string) $rev,
                (string) $cogs,
                (string) $opex,
                (string) $ebitda,
                (string) $capex,
                (string) $assets,
                (string) $liab,
                (string) $equity,
                (string) $marginBps,
            ];
        }

        $totalEquity = $totalAssets - $totalLiab;
        $groupMarginBps = ($totalRev > 0) ? (int) intdiv($totalEbitda * 10000, $totalRev) : 0;

        $rows[] = [
            'SUMMARY',
            "Total Segments: {$segments->count()}",
            (string) $totalRev,
            (string) $totalCogs,
            (string) $totalOpex,
            (string) $totalEbitda,
            (string) $totalCapex,
            (string) $totalAssets,
            (string) $totalLiab,
            (string) $totalEquity,
            (string) $groupMarginBps,
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "executive-consolidated-financial-summary-{$period}.csv",
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
