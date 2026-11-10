<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Reports;

use Modules\EnterpriseFinance\Domain\Models\DebtCovenantEvaluation;

/**
 * Exporter Laporan Kepatuhan Covenant Utang Sindikasi & Obligasi (D7).
 * Menyajikan evaluasi berkala rasio finansial wajib (DSCR >= 1.25x, DER <= 2.50x,
 * ISCR >= 2.00x, Current Ratio >= 1.10x) serta status kepatuhan hukum sindikasi perbankan.
 */
final class SyndicatedLoanCovenantComplianceReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $period): array
    {
        $evaluations = DebtCovenantEvaluation::with('facility')
            ->where('evaluation_period', $period)
            ->get();

        $rows = [];
        $rows[] = [
            'Evaluation Period',
            'Facility Code',
            'Facility Name',
            'Lead Arranger',
            'Currency',
            'Total Commitment Minor',
            'Outstanding Principal Minor',
            'DSCR (x1000)',
            'DER (x1000)',
            'ISCR (x1000)',
            'Current Ratio (x1000)',
            'Is Compliant',
            'SHA256 Signature',
            'Evaluated At',
        ];

        $compliantCount = 0;
        $breachCount = 0;

        foreach ($evaluations as $ev) {
            $fac = $ev->facility;
            if ($ev->is_compliant) {
                $compliantCount++;
            } else {
                $breachCount++;
            }

            $rows[] = [
                $ev->evaluation_period,
                $fac?->facility_code ?? '-',
                $fac?->facility_name ?? '-',
                $fac?->lead_arranger ?? '-',
                $fac?->currency ?? 'IDR',
                (string) ($fac?->total_commitment_minor ?? 0),
                (string) ($fac?->outstandingPrincipalMinor() ?? 0),
                (string) $ev->dscr_permille,
                (string) $ev->der_permille,
                (string) $ev->iscr_permille,
                (string) $ev->current_ratio_permille,
                $ev->is_compliant ? 'TRUE' : 'FALSE',
                $ev->sha256_signature,
                (string) $ev->created_at,
            ];
        }

        $rows[] = [
            'SUMMARY',
            "Total Evaluated: {$evaluations->count()}",
            "Compliant: {$compliantCount}",
            "Breached: {$breachCount}",
            '',
            '',
            '',
            'Min DSCR: 1250',
            'Max DER: 2500',
            'Min ISCR: 2000',
            'Min CR: 1100',
            ($breachCount === 0) ? 'ALL_COVENANTS_SATISFIED' : 'COVENANT_BREACH_ALERT',
            '',
            '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "syndicated-covenant-compliance-{$period}.csv",
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
