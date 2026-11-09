<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Billing;

use Modules\Hospital\Domain\Services\HospitalBillingVerticalAuditService;
use Modules\Hospital\Domain\Services\ClaimAgingAnalysisService;

/**
 * GenerateO2cKpiReportAction
 *
 * Mengkonsolidasikan metrik kinerja siklus Order-to-Cash (O2C) penagihan rumah sakit.
 */
class GenerateO2cKpiReportAction
{
    public function __construct(
        protected HospitalBillingVerticalAuditService $auditService,
        protected ClaimAgingAnalysisService $agingService
    ) {}

    /**
     * Eksekusi agregasi laporan O2C RS.
     *
     * @param int $grossRevenueCents
     * @param int $netCollectedCents
     * @param int $totalReceivablesCents
     * @param int $totalClaimsCount
     * @param int $firstPassCleanClaimsCount
     * @param int $deniedClaimsCount
     * @param int $operatingDays
     * @return array<string, mixed>
     */
    public function execute(
        int $grossRevenueCents,
        int $netCollectedCents,
        int $totalReceivablesCents,
        int $totalClaimsCount,
        int $firstPassCleanClaimsCount,
        int $deniedClaimsCount,
        int $operatingDays = 365
    ): array {
        $auditMetrics = $this->auditService->calculateRcmMetrics(
            $grossRevenueCents,
            $netCollectedCents,
            $totalReceivablesCents,
            $totalClaimsCount,
            $firstPassCleanClaimsCount,
            $deniedClaimsCount,
            $operatingDays
        );

        return [
            'period_days' => $operatingDays,
            'financial_summary' => [
                'gross_revenue_idr' => round($grossRevenueCents / 100, 2),
                'net_collected_idr' => round($netCollectedCents / 100, 2),
                'total_ar_idr' => round($totalReceivablesCents / 100, 2),
            ],
            'rcm_kpi' => $auditMetrics,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }
}
