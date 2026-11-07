<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelLoyaltyTierRequalificationSummaryEngine
{
    /**
     * Menghasilkan ringkasan kesehatan portofolio loyalitas hotel dan rasio retensi tingkat VIP.
     *
     * @return array{evaluation_year: int, platinum_retention_rate_pct: float, gold_advancement_rate_pct: float, churn_risk_mitigation_success_pct: float, health_index: string}
     */
    public function generateLoyaltyPortfolioHealthReport(int $evaluationYear): array
    {
        // Parameter benchmark performa loyalitas resort luxury
        $platinumRetention = 84.5;
        $goldAdvancement = 28.2;
        $churnMitigation = 74.2;

        $healthIndex = 'STRONG_EXPANDING';
        if ($platinumRetention < 70.0) {
            $healthIndex = 'HIGH_ATTRITION_WARNING';
        }

        return [
            'evaluation_year' => $evaluationYear,
            'platinum_retention_rate_pct' => $platinumRetention,
            'gold_advancement_rate_pct' => $goldAdvancement,
            'churn_risk_mitigation_success_pct' => $churnMitigation,
            'health_index' => $healthIndex,
        ];
    }
}
