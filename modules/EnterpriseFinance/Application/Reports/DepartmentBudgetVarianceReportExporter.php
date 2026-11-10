<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Reports;

use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;

/**
 * Exporter Laporan Analisis Varians Anggaran Departemen (D9).
 * Memuat perbandingan pagu alokasi, komitmen terreservasi (encumbrance), belanja aktual,
 * sisa pagu tersedia, dan tingkat penyerapan anggaran per cost center.
 */
final class DepartmentBudgetVarianceReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $fiscalYear): array
    {
        $budgets = EnterpriseBudget::where('fiscal_year', $fiscalYear)
            ->orderBy('cost_center_code')
            ->get();

        $rows = [];
        $rows[] = [
            'Fiscal Year',
            'Budget Code',
            'Cost Center Code',
            'Account Code',
            'Control Type',
            'Status',
            'Allocated IDR',
            'Encumbered IDR',
            'Spent IDR',
            'Total Utilized IDR',
            'Available IDR',
            'Utilization Permille',
            'Variance Status',
        ];

        $totalAllocated = 0;
        $totalEncumbered = 0;
        $totalSpent = 0;

        foreach ($budgets as $b) {
            $allocated = (int) $b->allocated_amount_idr;
            $encumbered = (int) $b->encumbered_amount_idr;
            $spent = (int) $b->spent_amount_idr;
            $utilized = $encumbered + $spent;
            $available = max(0, $allocated - $utilized);

            $permille = ($allocated > 0) ? (int) intdiv($utilized * 1000, $allocated) : 0;
            $varianceStatus = ($utilized > $allocated) ? 'DEFICIT_BREACH' : (($permille >= 900) ? 'HIGH_UTILIZATION' : 'ON_TRACK');

            $totalAllocated += $allocated;
            $totalEncumbered += $encumbered;
            $totalSpent += $spent;

            $rows[] = [
                $b->fiscal_year,
                $b->budget_code,
                $b->cost_center_code,
                $b->account_code,
                $b->control_type,
                $b->status ?? 'ACTIVE',
                (string) $allocated,
                (string) $encumbered,
                (string) $spent,
                (string) $utilized,
                (string) $available,
                (string) $permille,
                $varianceStatus,
            ];
        }

        $totalUtilized = $totalEncumbered + $totalSpent;
        $totalAvailable = max(0, $totalAllocated - $totalUtilized);
        $totalPermille = ($totalAllocated > 0) ? (int) intdiv($totalUtilized * 1000, $totalAllocated) : 0;

        $rows[] = [
            'SUMMARY',
            "Total Cost Centers: {$budgets->count()}",
            '',
            '',
            '',
            'CORPORATE TOTAL',
            (string) $totalAllocated,
            (string) $totalEncumbered,
            (string) $totalSpent,
            (string) $totalUtilized,
            (string) $totalAvailable,
            (string) $totalPermille,
            ($totalUtilized > $totalAllocated) ? 'OVERALL_DEFICIT' : 'WITHIN_BUDGET',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "department-budget-variance-{$fiscalYear}.csv",
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
