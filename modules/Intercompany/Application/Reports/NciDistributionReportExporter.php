<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Reports;

use Modules\Intercompany\Domain\Models\NciDividend;
use Modules\Intercompany\Domain\Models\SubsidiaryNci;

/**
 * Exporter Laporan Kepentingan Non-Pengendali (NCI / Minority Interest) & Distribusi Dividen.
 * Memuat persentase kepemilikan minoritas per anak perusahaan, laba bersih diatribusikan ke NCI,
 * dividen yang diumumkan vs dibayarkan, dan saldo ekuitas NCI pada laporan posisi keuangan konsolidasian.
 */
final class NciDistributionReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $fiscalYear): array
    {
        $ncis = SubsidiaryNci::orderBy('subsidiary_entity')->get();

        $rows = [];
        $rows[] = [
            'Fiscal Year',
            'Subsidiary Entity',
            'Parent Ownership (%)',
            'NCI Ownership (%)',
            'Parent Ownership (permille)',
            'NCI Ownership (permille)',
            'Subsidiary Net Income IDR',
            'NCI Net Income Share IDR',
            'NCI Dividends Declared IDR',
            'NCI Dividends Paid IDR',
            'NCI Cumulative Equity Balance IDR',
            'Status',
        ];

        $totalSubsidiaryIncome = 0;
        $totalNciShare = 0;
        $totalDividendsPaid = 0;
        $totalCumulativeEquity = 0;

        foreach ($ncis as $nci) {
            $dividends = NciDividend::where('subsidiary_nci_id', $nci->id)
                ->whereYear('declaration_date', (int) $fiscalYear)
                ->get();

            $divPaid = (int) $dividends->where('status', 'paid')->sum('nci_dividend_amount_idr');
            $divDeclared = (int) $dividends->sum('nci_dividend_amount_idr');

            $netIncome = (int) $nci->subsidiary_net_income_idr;
            $nciShare = (int) $nci->nci_share_idr;
            $cumulativeEquity = (int) $nci->nci_equity_balance_idr;

            $totalSubsidiaryIncome += $netIncome;
            $totalNciShare += $nciShare;
            $totalDividendsPaid += $divPaid;
            $totalCumulativeEquity += $cumulativeEquity;

            $rows[] = [
                $fiscalYear,
                $nci->subsidiary_entity,
                (string) $nci->parent_ownership_percent,
                (string) $nci->nci_ownership_percent,
                (string) ($nci->parent_ownership_permille ?? (int) ($nci->parent_ownership_percent * 1000)),
                (string) ($nci->nci_ownership_permille ?? (int) ($nci->nci_ownership_percent * 1000)),
                (string) $netIncome,
                (string) $nciShare,
                (string) $divDeclared,
                (string) $divPaid,
                (string) $cumulativeEquity,
                'ACTIVE',
            ];
        }

        $rows[] = [
            'SUMMARY',
            "Total Subsidiaries: {$ncis->count()}",
            '',
            '',
            '',
            '',
            (string) $totalSubsidiaryIncome,
            (string) $totalNciShare,
            '',
            (string) $totalDividendsPaid,
            (string) $totalCumulativeEquity,
            'BALANCED',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "nci-distribution-report-FY{$fiscalYear}.csv",
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
