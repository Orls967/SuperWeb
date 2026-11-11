<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Reports;

use Modules\EnterpriseFinance\Domain\Models\SodRule;

/**
 * Exporter Laporan Matriks Pemisahan Tugas (Segregation of Duties - SoD) & Tata Kelola Korporat (54.4).
 * Memuat aturan kombinasi peran terlarang (Maker-Checker, Approval vs Disbursement, etc.),
 * tingkat risiko pelanggaran (CRITICAL, HIGH, MEDIUM), dan status pengendalian mitigasi.
 */
final class SegregationOfDutiesMatrixReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(): array
    {
        $rules = SodRule::orderBy('risk_level')->orderBy('rule_code')->get();

        $rows = [];
        $rows[] = [
            'Rule Code',
            'Conflicting Role A',
            'Conflicting Role B',
            'Risk Level',
            'Description',
            'Is Active',
            'Enforcement Policy',
            'Created At',
        ];

        $criticalCount = 0;
        $highCount = 0;

        foreach ($rules as $r) {
            if ($r->risk_level === 'CRITICAL') {
                $criticalCount++;
            } elseif ($r->risk_level === 'HIGH') {
                $highCount++;
            }

            $rows[] = [
                $r->rule_code,
                $r->role_a,
                $r->role_b,
                $r->risk_level,
                $r->description,
                $r->is_active ? 'ACTIVE' : 'INACTIVE',
                ($r->risk_level === 'CRITICAL') ? 'STRICT_BLOCK' : 'AUDIT_LOG_EXCEPTION',
                (string) $r->created_at,
            ];
        }

        $rows[] = [
            'SUMMARY',
            "Total Rules: {$rules->count()}",
            "Critical: {$criticalCount}",
            "High: {$highCount}",
            'Governance Framework: COSO / ISO 27001',
            'Strict Hard-Stop Enforced',
            '',
            '',
        ];

        $csv = $this->buildCsv($rows);

        $date = date('Y-m-d');

        return [
            'filename' => "sod-matrix-compliance-{$date}.csv",
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
