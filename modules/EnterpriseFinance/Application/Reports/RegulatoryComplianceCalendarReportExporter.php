<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Reports;

use Modules\EnterpriseFinance\Domain\Models\ComplianceDeadline;

/**
 * Exporter Laporan Kalender Kepatuhan Regulasi & Batas Waktu Pelaporan Otoritas (54.6).
 * Memuat agenda pelaporan wajib ke DJP (SPT Masa/Tahunan), OJK, Bursa Efek Indonesia (BEI),
 * dan Kementerian BUMN/Kemendag beserta eskalasi SLA penanggung jawab.
 */
final class RegulatoryComplianceCalendarReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $fiscalYear): array
    {
        $deadlines = ComplianceDeadline::where('due_date', 'like', "{$fiscalYear}%")
            ->orderBy('due_date')
            ->get();

        $rows = [];
        $rows[] = [
            'Item Code',
            'Title',
            'Regulatory Body',
            'Due Date',
            'Assigned Role',
            'Status',
            'Urgency Status',
            'Created At',
        ];

        $pendingCount = 0;
        $completedCount = 0;
        $overdueCount = 0;
        $today = date('Y-m-d');

        foreach ($deadlines as $d) {
            $isOverdue = ($d->due_date < $today && $d->status !== 'completed');
            if ($d->status === 'completed') {
                $completedCount++;
                $urgency = 'RESOLVED';
            } elseif ($isOverdue) {
                $overdueCount++;
                $urgency = 'OVERDUE_BREACH';
            } else {
                $pendingCount++;
                $urgency = 'PENDING';
            }

            $rows[] = [
                $d->item_code,
                $d->title,
                $d->regulatory_body,
                $d->due_date,
                $d->assigned_role,
                $d->status,
                $urgency,
                (string) $d->created_at,
            ];
        }

        $rows[] = [
            'SUMMARY',
            "Total Regulatory Items: {$deadlines->count()}",
            "Completed: {$completedCount}",
            "Pending: {$pendingCount}",
            "Overdue: {$overdueCount}",
            ($overdueCount === 0) ? 'COMPLIANCE_ON_SCHEDULE' : 'CRITICAL_DEADLINE_BREACH',
            '',
            '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "regulatory-compliance-calendar-{$fiscalYear}.csv",
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
