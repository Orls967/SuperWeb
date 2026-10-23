<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\EnterpriseFinance\Domain\Models\EfSegmentFinancialReport;

class ConsolidateGroupMultiEntityTrialBalanceAction
{
    /**
     * @param  string  $periodQuarter  E.g. '2026-Q3'
     * @param array<string, array{
     *     revenue_minor: int,
     *     ebitda_minor: int,
     *     assets_minor?: int,
     *     liabilities_minor?: int
     * }> $entityBalances
     * @return array{
     *     report: EfSegmentFinancialReport,
     *     total_revenue_idr: int,
     *     total_ebitda_idr: int,
     *     entity_count: int
     * }
     */
    public function execute(string $periodQuarter, array $entityBalances): array
    {
        if (empty($entityBalances)) {
            throw new InvalidArgumentException('Entity balances cannot be empty for group trial balance consolidation.');
        }

        $totalRevenue = 0;
        $totalEbitda = 0;

        foreach ($entityBalances as $entity => $data) {
            $totalRevenue += (int) ($data['revenue_minor'] ?? 0);
            $totalEbitda += (int) ($data['ebitda_minor'] ?? 0);
        }

        $reportCode = 'CR-'.str_replace('-', '', $periodQuarter).'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $report = EfSegmentFinancialReport::updateOrCreate(
            ['period_quarter' => $periodQuarter],
            [
                'id' => (string) Str::uuid(),
                'report_code' => $reportCode,
                'segment_contributions' => $entityBalances,
                'total_consolidated_revenue_minor' => $totalRevenue,
                'total_consolidated_ebitda_minor' => $totalEbitda,
            ]
        );

        return [
            'report' => $report,
            'total_revenue_idr' => $totalRevenue,
            'total_ebitda_idr' => $totalEbitda,
            'entity_count' => count($entityBalances),
        ];
    }
}
