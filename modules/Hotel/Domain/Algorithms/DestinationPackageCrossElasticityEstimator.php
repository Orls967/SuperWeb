<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final class DestinationPackageCrossElasticityEstimator
{
    /**
     * Memperkirakan elastisitas silang permintaan antara komponen kamar hotel dan tiket atraksi venue ketika terjadi perubahan harga bundel.
     *
     * @return array{
     *     bundle_code: string,
     *     price_change_pct: float,
     *     predicted_hotel_demand_delta_pct: float,
     *     predicted_venue_demand_delta_pct: float,
     *     elasticity_classification: string,
     *     is_complementary_bundle: bool
     * }
     */
    public function estimateElasticity(
        string $bundleCode,
        float $priceChangePercentage,
        float $historicalCrossElasticityCoefficient = -0.75
    ): array {
        // Barang komplementer memiliki koefisien elastisitas silang negatif
        $hotelDemandDelta = round($priceChangePercentage * -1.2, 2);
        $venueDemandDelta = round($priceChangePercentage * $historicalCrossElasticityCoefficient, 2);

        $classification = 'INELASTIC_STABLE';
        if (abs($hotelDemandDelta) > 15.0) {
            $classification = 'HIGHLY_ELASTIC_VOLATILE';
        } elseif (abs($hotelDemandDelta) > 8.0) {
            $classification = 'MODERATELY_ELASTIC';
        }

        return [
            'bundle_code' => $bundleCode,
            'price_change_pct' => $priceChangePercentage,
            'predicted_hotel_demand_delta_pct' => $hotelDemandDelta,
            'predicted_venue_demand_delta_pct' => $venueDemandDelta,
            'elasticity_classification' => $classification,
            'is_complementary_bundle' => $historicalCrossElasticityCoefficient < 0,
        ];
    }
}
