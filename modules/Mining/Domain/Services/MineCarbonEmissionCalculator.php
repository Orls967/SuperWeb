<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use InvalidArgumentException;

/**
 * Kalkulator Jejak Karbon & Emisi GRK Operasional Tambang (D94-6, CBAM).
 *
 * Mengikuti metodologi IPCC Guidelines for National Greenhouse Gas Inventories & ISO 14064:
 * - Scope 1: Emisi langsung pembakaran BBM solar (Diesel B35/B40) alat berat tambang (faktor emisi ~2.68 kg CO2e / liter).
 * - Scope 2: Emisi tidak langsung konsumsi tenaga listrik PLN/PLTU grid smelter (faktor emisi ~0.78 kg CO2e / kWh).
 * - Intensitas Emisi: kg CO2e per ton komoditas tertambang / terproses.
 */
final class MineCarbonEmissionCalculator
{
    /** Faktor emisi BBM solar industri (kg CO2e per liter) */
    private const EMISSION_FACTOR_DIESEL_KG_PER_LITER = 2.68;

    /** Faktor emisi listrik grid Indonesia (kg CO2e per kWh) */
    private const EMISSION_FACTOR_GRID_ELECTRICITY_KG_PER_KWH = 0.78;

    /**
     * @param array{
     *     diesel_consumed_liters: int,
     *     electricity_consumed_kwh: int,
     *     explosives_anfo_kg?: int,
     *     total_production_tons: int
     * } $data
     * @return array{
     *     scope1_diesel_emissions_kg_co2e: float,
     *     scope1_explosives_emissions_kg_co2e: float,
     *     total_scope1_kg_co2e: float,
     *     scope2_electricity_emissions_kg_co2e: float,
     *     total_gross_emissions_kg_co2e: float,
     *     total_gross_emissions_ton_co2e: float,
     *     emission_intensity_kg_co2e_per_ton: float,
     *     cbam_benchmark_status: string
     * }
     */
    public function calculate(array $data): array
    {
        $dieselLiters = (int) $data['diesel_consumed_liters'];
        $electricityKwh = (int) $data['electricity_consumed_kwh'];
        $anfoKg = (int) ($data['explosives_anfo_kg'] ?? 0);
        $productionTons = (int) $data['total_production_tons'];

        if ($productionTons <= 0) {
            throw new InvalidArgumentException('Total tonase produksi tambang harus lebih besar dari nol.');
        }

        // Scope 1: Diesel combustion
        $scope1Diesel = round($dieselLiters * self::EMISSION_FACTOR_DIESEL_KG_PER_LITER, 2);

        // Scope 1: Bahan peledak ANFO (~0.17 kg CO2e per kg ANFO)
        $scope1Explosives = round($anfoKg * 0.17, 2);

        $totalScope1 = round($scope1Diesel + $scope1Explosives, 2);

        // Scope 2: Listrik PLN / PLTU konsesi
        $scope2Electricity = round($electricityKwh * self::EMISSION_FACTOR_GRID_ELECTRICITY_KG_PER_KWH, 2);

        $totalGrossKg = round($totalScope1 + $scope2Electricity, 2);
        $totalGrossTons = round($totalGrossKg / 1000, 3);

        $intensity = round($totalGrossKg / $productionTons, 2);

        // Standar intensitas CBAM / Ditjen EBTKE ESDM (< 25 kg CO2e/ton batubara, < 80 kg CO2e/ton bijih)
        $cbamStatus = ($intensity <= 30.0) ? 'EXCELLENT_LOW_CARBON' : (($intensity <= 60.0) ? 'MODERATE_ACCEPTABLE' : 'HIGH_EMISSION_TAX_RISK');

        return [
            'scope1_diesel_emissions_kg_co2e' => $scope1Diesel,
            'scope1_explosives_emissions_kg_co2e' => $scope1Explosives,
            'total_scope1_kg_co2e' => $totalScope1,
            'scope2_electricity_emissions_kg_co2e' => $scope2Electricity,
            'total_gross_emissions_kg_co2e' => $totalGrossKg,
            'total_gross_emissions_ton_co2e' => $totalGrossTons,
            'emission_intensity_kg_co2e_per_ton' => $intensity,
            'cbam_benchmark_status' => $cbamStatus,
        ];
    }
}
