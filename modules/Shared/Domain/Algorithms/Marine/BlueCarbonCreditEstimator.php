<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Marine;

use DomainException;

/**
 * A18: BlueCarbonCreditEstimator.
 *
 * Mengestimasi penyerapan karbon ekosistem mangrove dan lamun pesisir
 * berdasarkan metodologi Verra VM0033 (Tidal Wetland Restoration & Silvofishery).
 * Menghitung stok karbon AGB (Aboveground Biomass), BGB (Belowground Biomass),
 * dan SOC (Soil Organic Carbon), dikurangi emisi baseline dan buffer pool risiko.
 */
class BlueCarbonCreditEstimator
{
    // Rasio konversi biomassa kering ke karbon elemental (C fraction IPCC = 0.47)
    public const CARBON_FRACTION = 0.47;

    // Rasio stoikiometri molekuler C ke CO2 (44 / 12)
    public const CO2_TO_C_RATIO = 3.667;

    // Rasio Biomassa Bawah Tanah (BGB/Akar) terhadap AGB untuk mangrove tropis (Komiyama et al.)
    public const ROOT_TO_SHOOT_RATIO = 0.49;

    /**
     * Estimasi kredit karbon mangrove silvofishery / restorasi pesisir.
     *
     * @param  string  $species  'Rhizophora'|'Avicennia'|'Sonneratia'|'Mixed'
     * @param  float  $areaHectares  Luas area restorasi / greenbelt
     * @param  float  $meanDbhCm  Diameter pohon setinggi dada (cm)
     * @param  int  $treeDensityPerHectare  Kerapatan tegakan pohon per hektar
     * @param  float  $soilDepthCm  Kedalaman sampling tanah sediment (misal 100 cm)
     * @param  float  $soilBulkDensityGramsPerCm3  Berat jenis tanah sedimen kering (g/cm³)
     * @param  float  $soilOrganicCarbonPercentage  Persentase karbon organik tanah (% C)
     * @param  float  $bufferRiskFraction  Potongan cadangan risiko ketidakpastian (misal 0.15 = 15%)
     * @return array{
     *     aboveground_biomass_t_per_ha: float,
     *     belowground_biomass_t_per_ha: float,
     *     soil_carbon_t_per_ha: float,
     *     total_carbon_stock_t_per_ha: float,
     *     gross_co2_equivalent_total_tons: float,
     *     buffer_risk_deduction_tons: float,
     *     net_marketable_carbon_credits_tco2e: float,
     *     methodology_standard: string
     * }
     */
    public function estimateBlueCarbon(
        string $species,
        float $areaHectares,
        float $meanDbhCm,
        int $treeDensityPerHectare,
        float $soilDepthCm = 100.0,
        float $soilBulkDensityGramsPerCm3 = 0.85,
        float $soilOrganicCarbonPercentage = 2.5,
        float $bufferRiskFraction = 0.15
    ): array {
        if ($areaHectares <= 0.0 || $meanDbhCm <= 0.0 || $treeDensityPerHectare <= 0) {
            throw new DomainException('Parameter luas area, diameter pohon, dan kerapatan harus positif.');
        }

        if ($soilBulkDensityGramsPerCm3 <= 0.0 || $soilOrganicCarbonPercentage < 0.0) {
            throw new DomainException('Parameter tanah sedimen tidak valid.');
        }

        // 1. Allometric biomass per tree (kg) berdasarkan spesies mangrove (Chave et al. / Komiyama)
        // W_top = a * (DBH ^ b)
        $biomassPerTreeKg = match (strtolower($species)) {
            'rhizophora' => 0.128 * ($meanDbhCm ** 2.45),
            'avicennia' => 0.184 * ($meanDbhCm ** 2.35),
            'sonneratia' => 0.168 * ($meanDbhCm ** 2.38),
            default => 0.150 * ($meanDbhCm ** 2.40),
        };

        // 2. AGB per hektar (ton/ha) = (biomass_tree_kg * trees_per_ha) / 1000
        $agbPerHaTon = ($biomassPerTreeKg * $treeDensityPerHectare) / 1000.0;

        // 3. BGB per hektar (ton/ha)
        $bgbPerHaTon = $agbPerHaTon * self::ROOT_TO_SHOOT_RATIO;

        // 4. Stok Karbon Biomassa Pohon (tC/ha)
        $vegetationCarbonPerHaTon = ($agbPerHaTon + $bgbPerHaTon) * self::CARBON_FRACTION;

        // 5. Soil Organic Carbon (SOC tC/ha)
        // SOC (tC/ha) = Depth (cm) * BulkDensity (g/cm³) * (%C / 100) * 100
        $soilCarbonPerHaTon = $soilDepthCm * $soilBulkDensityGramsPerCm3 * ($soilOrganicCarbonPercentage / 100.0) * 100.0;

        $totalCarbonStockPerHaTon = $vegetationCarbonPerHaTon + $soilCarbonPerHaTon;

        // Total Gross CO2e (ton) seluruh area proyek
        $totalGrossCo2eTons = $totalCarbonStockPerHaTon * $areaHectares * self::CO2_TO_C_RATIO;

        // Potongan buffer risk (Verra AFOLU non-permanence risk pool)
        $bufferDeductionTons = $totalGrossCo2eTons * max(0.05, min(0.30, $bufferRiskFraction));
        $netCreditsTco2e = max(0.0, $totalGrossCo2eTons - $bufferDeductionTons);

        return [
            'aboveground_biomass_t_per_ha' => round($agbPerHaTon, 2),
            'belowground_biomass_t_per_ha' => round($bgbPerHaTon, 2),
            'soil_carbon_t_per_ha' => round($soilCarbonPerHaTon, 2),
            'total_carbon_stock_t_per_ha' => round($totalCarbonStockPerHaTon, 2),
            'gross_co2_equivalent_total_tons' => round($totalGrossCo2eTons, 2),
            'buffer_risk_deduction_tons' => round($bufferDeductionTons, 2),
            'net_marketable_carbon_credits_tco2e' => round($netCreditsTco2e, 2),
            'methodology_standard' => 'VERRA_VM0033_TIDAL_WETLAND_SILVOFISHERY',
        ];
    }
}
