<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Vendor;

/**
 * Vendor Concentration Risk & Herfindahl-Hirschman Index Evaluator (W8-D57).
 *
 * Evaluates vendor concentration risk across the enterprise to prevent excessive
 * reliance on a single vendor cluster or supply chain counterparty.
 *
 * Implements HHI formula:
 *   HHI = sum( (market_share_percentage_i)^2 )
 *
 * Classifications:
 * - HHI < 1500: Unconcentrated (Healthy diversification)
 * - 1500 <= HHI <= 2500: Moderately Concentrated
 * - HHI > 2500: Highly Concentrated (Critical risk)
 */
final class VendorConcentrationRiskEvaluator
{
    /**
     * Compute Herfindahl-Hirschman Index (HHI) and concentration metrics.
     *
     * @param  array<string, int>  $clusterExposureTotals  Map of vendor cluster code => total exposure IDR
     * @return array{
     *     total_enterprise_exposure_idr: int,
     *     cluster_count: int,
     *     hhi_score: float,
     *     concentration_category: string,
     *     top_cluster_code: ?string,
     *     top_cluster_share_percent: float,
     *     requires_diversification_plan: bool
     * }
     */
    public function evaluateConcentration(array $clusterExposureTotals): array
    {
        $totalExposure = array_sum($clusterExposureTotals);
        $clusterCount = count($clusterExposureTotals);

        if ($totalExposure <= 0 || $clusterCount === 0) {
            return [
                'total_enterprise_exposure_idr' => 0,
                'cluster_count' => 0,
                'hhi_score' => 0.0,
                'concentration_category' => 'UNCONCENTRATED',
                'top_cluster_code' => null,
                'top_cluster_share_percent' => 0.0,
                'requires_diversification_plan' => false,
            ];
        }

        $hhi = 0.0;
        $topCluster = null;
        $maxShare = 0.0;

        foreach ($clusterExposureTotals as $cluster => $amount) {
            $share = ($amount / $totalExposure) * 100;
            $hhi += ($share * $share);

            if ($share > $maxShare) {
                $maxShare = $share;
                $topCluster = $cluster;
            }
        }

        $hhi = round($hhi, 2);
        $category = match (true) {
            $hhi > 2500 => 'HIGHLY_CONCENTRATED',
            $hhi >= 1500 => 'MODERATELY_CONCENTRATED',
            default => 'UNCONCENTRATED',
        };

        $requiresPlan = ($category === 'HIGHLY_CONCENTRATED') || ($maxShare >= 35.0);

        return [
            'total_enterprise_exposure_idr' => $totalExposure,
            'cluster_count' => $clusterCount,
            'hhi_score' => $hhi,
            'concentration_category' => $category,
            'top_cluster_code' => $topCluster,
            'top_cluster_share_percent' => round($maxShare, 2),
            'requires_diversification_plan' => $requiresPlan,
        ];
    }
}
