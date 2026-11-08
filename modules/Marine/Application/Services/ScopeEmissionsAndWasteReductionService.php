<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Models\AquacultureBatch;

/**
 * Service pemantauan emisi karbon Scope 1, 2, 3 dan manajemen limbah tambak (Fase 171 Sub-item 171.3).
 */
class ScopeEmissionsAndWasteReductionService
{
    // Faktor emisi IPCC baku
    public const DIESEL_EMISSION_FACTOR_KG_CO2_PER_LITER = 2.68;

    public const GRID_ELECTRICITY_EMISSION_FACTOR_KG_PER_KWH = 0.78; // Grid PLN Jawa-Bali/Kalimantan

    public const FEED_EMISSION_FACTOR_KG_CO2_PER_KG_FEED = 1.65; // Pakan ASC tersertifikasi non-deforestasi

    /**
     * Hitung total jejak karbon (Carbon Footprint) satu siklus budidaya tambak udang.
     *
     * @return array{
     *     batch_code: string,
     *     scope1_diesel_emissions_kg_co2: float,
     *     scope2_electricity_emissions_kg_co2: float,
     *     scope3_feed_embedded_emissions_kg_co2: float,
     *     total_carbon_footprint_tonnes_co2e: float,
     *     carbon_intensity_kg_co2_per_kg_shrimp: float,
     *     waste_sludge_diverted_tonnes: float,
     *     esg_compliance_rating: string
     * }
     */
    public function calculateBatchCarbonFootprint(
        int $batchId,
        float $dieselConsumedLiters,
        float $electricityConsumedKwh,
        float $totalFeedUsedKg,
        float $totalHarvestBiomassKg,
        float $sludgeCompostedKg = 1500.0
    ): array {
        $batch = AquacultureBatch::findOrFail($batchId);

        if ($totalHarvestBiomassKg <= 0.0) {
            throw new DomainException('Biomassa panen udang harus lebih besar dari 0 kg.');
        }

        // 1. Scope 1 (Diesel genset & pompa)
        $scope1 = $dieselConsumedLiters * self::DIESEL_EMISSION_FACTOR_KG_CO2_PER_LITER;

        // 2. Scope 2 (Listrik kincir aerasi)
        $scope2 = $electricityConsumedKwh * self::GRID_ELECTRICITY_EMISSION_FACTOR_KG_PER_KWH;

        // 3. Scope 3 (Emisi bahan pakan akuakultur)
        $scope3 = $totalFeedUsedKg * self::FEED_EMISSION_FACTOR_KG_CO2_PER_KG_FEED;

        $totalKgCo2 = $scope1 + $scope2 + $scope3;
        $totalTonnesCo2 = round($totalKgCo2 / 1000.0, 3);
        $intensity = round($totalKgCo2 / $totalHarvestBiomassKg, 2);

        $sludgeDivertedTonnes = round($sludgeCompostedKg / 1000.0, 2);

        $rating = 'ESG_GOLD_LOW_CARBON';
        if ($intensity > 4.5) {
            $rating = 'ESG_CRITICAL_HIGH_INTENSITY';
        } elseif ($intensity > 3.0) {
            $rating = 'ESG_SILVER_MODERATE';
        }

        return [
            'batch_code' => $batch->batch_code,
            'scope1_diesel_emissions_kg_co2' => round($scope1, 2),
            'scope2_electricity_emissions_kg_co2' => round($scope2, 2),
            'scope3_feed_embedded_emissions_kg_co2' => round($scope3, 2),
            'total_carbon_footprint_tonnes_co2e' => $totalTonnesCo2,
            'carbon_intensity_kg_co2_per_kg_shrimp' => $intensity,
            'waste_sludge_diverted_tonnes' => $sludgeDivertedTonnes,
            'esg_compliance_rating' => $rating,
        ];
    }
}
