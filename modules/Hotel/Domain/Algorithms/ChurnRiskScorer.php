<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Algoritma Bersertifikat H9: ChurnRiskScorer.
 * Menghitung skor atrisi tamu hotel berbasis Recency, Frequency, Monetary,
 * serta sentimen riwayat keluhan pelayanan.
 */
final class ChurnRiskScorer
{
    public function score(
        string $guestId,
        int $daysSinceLastStay,
        int $totalStaysPastYear,
        int $averageSpendPerStayIdr,
        int $unresolvedComplaintsCount,
        float $averageReviewRating
    ): ChurnRiskCertificate {
        // 1. Recency Penalty (Makin lama tidak menginap, risiko makin tinggi)
        $recencyScore = min(1.0, max(0.0, $daysSinceLastStay / 180.0));

        // 2. Frequency Retention (Makin sering menginap, risiko makin rendah)
        $frequencyRetention = min(1.0, max(0.0, $totalStaysPastYear / 6.0));
        $frequencyRisk = 1.0 - $frequencyRetention;

        // 3. Complaint Impact (Keluhan yang belum selesai meningkatkan risiko signifikan)
        $complaintRisk = min(1.0, max(0.0, $unresolvedComplaintsCount * 0.35));

        // 4. Rating Sentiment (Rating di bawah 4.0 meningkatkan risiko)
        $normalizedRating = min(5.0, max(1.0, $averageReviewRating));
        $ratingRisk = max(0.0, (5.0 - $normalizedRating) / 4.0);

        // Gabungan Bobot: Recency 30%, Frequency 25%, Complaint 30%, Rating 15%
        $compositeScore = (0.30 * $recencyScore)
            + (0.25 * $frequencyRisk)
            + (0.30 * $complaintRisk)
            + (0.15 * $ratingRisk);

        $finalScore = (float) round(min(1.0, max(0.0, $compositeScore)), 4);

        [$riskCategory, $actionPlan] = match (true) {
            $finalScore >= 0.65 => [
                'HIGH_RISK',
                [
                    'priority' => 'CRITICAL',
                    'action' => 'DIRECT_GM_CONCIERGE_OUTREACH',
                    'incentive' => 'COMPLIMENTARY_WEEKEND_STAY_OR_FNB_VOUCHER_500K',
                    'tag' => 'RETENTION_URGENT',
                ],
            ],
            $finalScore >= 0.30 => [
                'MEDIUM_RISK',
                [
                    'priority' => 'HIGH',
                    'action' => 'SEND_PERSONALIZED_WINBACK_OFFER',
                    'incentive' => 'ROOM_DISCOUNT_20PCT_AND_DOUBLE_POINTS',
                    'tag' => 'RETENTION_PROACTIVE',
                ],
            ],
            default => [
                'LOW_RISK',
                [
                    'priority' => 'STANDARD',
                    'action' => 'REGULAR_LOYALTY_NURTURING',
                    'incentive' => 'STANDARD_TIER_POINTS',
                    'tag' => 'LOYAL_ACTIVE',
                ],
            ],
        };

        $checksum = hash('sha256', sprintf(
            '%s|%.4f|%s|%d|%d|%d',
            $guestId,
            $finalScore,
            $riskCategory,
            $daysSinceLastStay,
            $totalStaysPastYear,
            $unresolvedComplaintsCount
        ));

        return new ChurnRiskCertificate(
            guestId: $guestId,
            churnScore: $finalScore,
            riskCategory: $riskCategory,
            daysSinceLastStay: $daysSinceLastStay,
            totalStaysPastYear: $totalStaysPastYear,
            unresolvedComplaintsCount: $unresolvedComplaintsCount,
            actionPlan: $actionPlan,
            checksum: $checksum
        );
    }
}
