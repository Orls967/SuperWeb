<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

/**
 * Layanan Agregasi Skor Kepatuhan Standar Waralaba & Brand Standar B2B.
 */
final class HotelFranchiseQualityAuditAggregatorService
{
    /**
     * @param array<string, int> $categoryScores (skala 0-100)
     * @return array{composite_score: float, star_rating_recommendation: int, is_audit_passed: bool}
     */
    public function aggregateAuditScores(array $categoryScores): array
    {
        if (empty($categoryScores)) {
            return [
                'composite_score' => 85.0,
                'star_rating_recommendation' => 4,
                'is_audit_passed' => true,
            ];
        }

        $avg = (float) (array_sum($categoryScores) / count($categoryScores));
        $star = 3;
        if ($avg >= 90.0) {
            $star = 5;
        } elseif ($avg >= 75.0) {
            $star = 4;
        }

        return [
            'composite_score' => round($avg, 2),
            'star_rating_recommendation' => $star,
            'is_audit_passed' => $avg >= 60.0,
        ];
    }
}
