<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;

class HotelBanquetWasteMitigationOptimizer
{
    /**
     * Menganalisis sisa makanan berlebih dari jamuan (Food Waste Audit) dan menghitung efisiensi penyajian prasmanan.
     *
     * @return array{contract_id: string, prepared_pax: int, actual_consumed_pax: int, waste_ratio_pct: float, waste_cost_idr: int, sustainability_rating: string, donation_eligible_kg: float}
     */
    public function auditBanquetFoodWaste(
        string $contractId,
        int $preparedPax,
        int $actualConsumedPax,
        int $costPerPaxIdr = 250000
    ): array {
        if ($preparedPax <= 0) {
            throw new InvalidArgumentException('Prepared pax harus lebih dari nol.');
        }

        $surplusPax = max(0, $preparedPax - $actualConsumedPax);
        $wasteRatioPct = round(($surplusPax / $preparedPax) * 100, 2);
        $wasteCostIdr = $surplusPax * $costPerPaxIdr;

        // Estimasi porsi berlebih yang higienis untuk didonasikan ke bank makanan lokal (0.35 kg per pax)
        $donationEligibleKg = round($surplusPax * 0.35, 1);

        $rating = 'EXCELLENT_MINIMAL_WASTE';
        if ($wasteRatioPct > 15.0) {
            $rating = 'HIGH_WASTE_DEFICIT';
        } elseif ($wasteRatioPct > 8.0) {
            $rating = 'MODERATE_ACCEPTABLE';
        }

        return [
            'contract_id' => $contractId,
            'prepared_pax' => $preparedPax,
            'actual_consumed_pax' => $actualConsumedPax,
            'waste_ratio_pct' => $wasteRatioPct,
            'waste_cost_idr' => $wasteCostIdr,
            'sustainability_rating' => $rating,
            'donation_eligible_kg' => $donationEligibleKg,
        ];
    }
}
