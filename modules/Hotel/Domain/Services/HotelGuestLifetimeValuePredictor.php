<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelGuestLifetimeValuePredictor
{
    /**
     * Memprediksi Customer Lifetime Value (CLV 3-Tahun) tamu berbasis data RFM dan probabilitas churn.
     *
     * @return array{guest_id: string, historical_spend_idr: int, predicted_future_spend_3y_idr: int, ltv_category: string, churn_discounted_ltv_idr: int}
     */
    public function predictGuestLtv(
        string $guestId,
        int $historicalSpendIdr,
        int $annualStayFrequency,
        int $averageDailySpendIdr,
        float $churnProbability
    ): array {
        // Estimasi masa depan: Frequency * ADR * 3 Tahun * faktor retensi (1 - churn)
        $grossProjection3Y = (int) ($annualStayFrequency * $averageDailySpendIdr * 3);
        $retentionFactor = max(0.1, 1.0 - $churnProbability);
        $discountedLtv = (int) round($grossProjection3Y * $retentionFactor);

        $category = 'STANDARD';
        if ($discountedLtv > 100000000) {
            $category = 'WHALE_ULTRA_VIP';
        } elseif ($discountedLtv > 50000000) {
            $category = 'PREMIUM_HIGH_VALUE';
        } elseif ($discountedLtv > 20000000) {
            $category = 'MID_TIER_GROWTH';
        }

        return [
            'guest_id' => $guestId,
            'historical_spend_idr' => $historicalSpendIdr,
            'predicted_future_spend_3y_idr' => $grossProjection3Y,
            'ltv_category' => $category,
            'churn_discounted_ltv_idr' => $discountedLtv,
        ];
    }
}
