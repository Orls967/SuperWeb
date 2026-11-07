<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelSpaTherapistProductivityScorer
{
    /**
     * Menghitung skor produktivitas kinerja terapis spa berdasarkan jam pelayanan, kepuasan tamu, dan penjualan produk aromaterapi.
     *
     * @return array{therapist_id: string, total_treatment_hours: float, average_rating: float, retail_upsell_idr: int, productivity_score: float, performance_tier: string}
     */
    public function evaluateTherapistScore(
        string $therapistId,
        float $treatmentHoursMonth,
        float $guestRatingScore, // 1.0 - 5.0
        int $retailProductSalesIdr
    ): array {
        // Bobot: 50% jam kerja, 30% rating tamu, 20% penjualan ritel
        $hourComponent = min(100.0, ($treatmentHoursMonth / 80.0) * 100) * 0.50;
        $ratingComponent = ($guestRatingScore / 5.0) * 100 * 0.30;
        $retailComponent = min(100.0, ($retailProductSalesIdr / 10000000.0) * 100) * 0.20;

        $compositeScore = round($hourComponent + $ratingComponent + $retailComponent, 2);

        $tier = 'STANDARD';
        if ($compositeScore >= 85.0) {
            $tier = 'MASTER_THERAPIST';
        } elseif ($compositeScore >= 70.0) {
            $tier = 'SENIOR_PRACTIONER';
        }

        return [
            'therapist_id' => $therapistId,
            'total_treatment_hours' => $treatmentHoursMonth,
            'average_rating' => $guestRatingScore,
            'retail_upsell_idr' => $retailProductSalesIdr,
            'productivity_score' => $compositeScore,
            'performance_tier' => $tier,
        ];
    }
}
