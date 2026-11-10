<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Reports;

use Modules\Treasury\Domain\Models\CreditFacility;

/**
 * Exporter Laporan Fasilitas Kredit Sindikasi & Pemantauan Covenant Rasional (CSV).
 */
final class CreditFacilityCovenantReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(): array
    {
        $facilities = CreditFacility::with('drawdowns')->orderBy('facility_code')->get();

        $rows = [];
        $rows[] = [
            'Facility Code', 'Entity ID', 'Bank Name', 'Credit Limit IDR',
            'Drawn Amount IDR', 'Available Limit IDR', 'Interest Rate Permil',
            'Max DER Permil', 'Utilization Pct', 'Total Drawdowns Count', 'Status',
        ];

        $totalLimit = 0;
        $totalDrawn = 0;

        foreach ($facilities as $fac) {
            $limit = (int) $fac->credit_limit_idr;
            $drawn = (int) $fac->drawn_amount_idr;
            $available = $limit - $drawn;

            $totalLimit += $limit;
            $totalDrawn += $drawn;

            $utilizationPermil = $limit > 0 ? (int) intdiv($drawn * 1000, $limit) : 0;
            $utilizationPct = sprintf('%.1f%%', $utilizationPermil / 10);

            $rows[] = [
                $fac->facility_code,
                $fac->entity_id,
                $fac->bank_name,
                (string) $limit,
                (string) $drawn,
                (string) $available,
                (string) $fac->interest_rate_permil,
                (string) $fac->max_der_permil,
                $utilizationPct,
                (string) $fac->drawdowns->count(),
                $fac->status,
            ];
        }

        $totalAvailable = $totalLimit - $totalDrawn;
        $totalUtilPermil = $totalLimit > 0 ? (int) intdiv($totalDrawn * 1000, $totalLimit) : 0;

        $rows[] = [
            'TOTAL', '', '', (string) $totalLimit, (string) $totalDrawn, (string) $totalAvailable,
            '', '', sprintf('%.1f%%', $totalUtilPermil / 10), '', '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => 'treasury-credit-facilities-covenant.csv',
            'content' => $csv,
            'row_count' => count($rows) - 2,
            'sha256' => hash('sha256', $csv),
        ];
    }

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
