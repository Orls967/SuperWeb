<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use InvalidArgumentException;

/**
 * Kalkulator Neraca Energi Tanur Peleburan Smelter RKEF (Rotary Kiln Electric Furnace) (D94-8).
 *
 * Mengkalkulasi konsumsi energi termal kalsinasi tanur putar dan daya listrik tungku busur:
 * 1. Panas Penguapan Kelembaban Bebas (Free Moisture Enthalpy): Delta H = m * 2260 kJ/kg
 * 2. Panas Dehidroksilasi Kisi Kristal Serpentin/Limonite: ~800 kJ/kg bijih kering
 * 3. Panas Reaksi Reduksi Oksida Besi & Nikel (NiO, Fe2O3) oleh Karbon Kokas
 * 4. Efisiensi Termal Tanur (Thermal Efficiency): Output Enthalpy / Input Energy.
 */
final class SmelterEnergyBalanceCalculator
{
    /**
     * @param array{
     *     dry_feed_tonnage: int,
     *     moisture_content_pct: float,
     *     coal_reductant_rate_kg_per_ton: int,
     *     coal_calorific_kcal: int,
     *     electric_power_kwh_per_ton: int,
     *     smelting_temperature_celsius?: int
     * } $data
     * @return array{
     *     total_wet_feed_tons: float,
     *     water_evaporation_gj: float,
     *     dehydroxylation_gj: float,
     *     coal_thermal_input_gj: float,
     *     electric_power_input_gj: float,
     *     total_energy_input_gj: float,
     *     specific_energy_consumption_gj_per_ton: float,
     *     furnace_thermal_efficiency_pct: float,
     *     energy_performance_verdict: string
     * }
     */
    public function calculate(array $data): array
    {
        $dryTons = (int) $data['dry_feed_tonnage'];
        $moisturePct = (float) $data['moisture_content_pct'];
        $coalRateKg = (int) $data['coal_reductant_rate_kg_per_ton'];
        $coalCalorie = (int) $data['coal_calorific_kcal'];
        $powerKwhPerTon = (int) $data['electric_power_kwh_per_ton'];
        $tempC = (int) ($data['smelting_temperature_celsius'] ?? 1550);

        if ($dryTons <= 0 || $moisturePct < 0 || $coalRateKg <= 0 || $coalCalorie <= 0 || $powerKwhPerTon <= 0) {
            throw new InvalidArgumentException('Parameter operasional tanur smelter harus bernilai positif.');
        }

        // 1. Total Wet Feed
        $wetTons = $dryTons / max(0.01, 1.0 - ($moisturePct / 100.0));
        $waterKg = ($wetTons - $dryTons) * 1000.0;

        // 2. Energi Penguapan Air (2.26 MJ/kg) dalam GigaJoule (GJ)
        $evaporationGj = round(($waterKg * 2.26) / 1000.0, 2);

        // 3. Energi Dehidroksilasi Mineral Lempung (0.8 MJ/kg kering) dalam GJ
        $dehydroxylationGj = round(($dryTons * 1000.0 * 0.8) / 1000.0, 2);

        // 4. Input Termal Batubara Reduktor: (Kg x Kcal x 4.184 kJ/Kcal) / 1e6 dalam GJ
        $totalCoalKg = $dryTons * $coalRateKg;
        $coalGj = round(($totalCoalKg * $coalCalorie * 4.184) / 1000000.0, 2);

        // 5. Input Daya Listrik: 1 kWh = 3.6 MJ = 0.0036 GJ
        $totalPowerKwh = $dryTons * $powerKwhPerTon;
        $electricGj = round($totalPowerKwh * 0.0036, 2);

        $totalInputGj = round($coalGj + $electricGj, 2);

        // Konsumsi Energi Spesifik per ton bijih kering (SEC)
        $secGjPerTon = round($totalInputGj / $dryTons, 2);

        // Efisiensi Termal = (Energi reaksi reduksi + peleburan terak) / Total Input
        $usefulThermalGj = $evaporationGj + $dehydroxylationGj + round($dryTons * 1.45, 2); // 1.45 GJ/ton panas peleburan logam
        $thermalEfficiency = min(95.0, round(($usefulThermalGj / max(1.0, $totalInputGj)) * 100.0, 2));

        $verdict = ($thermalEfficiency >= 65.0)
            ? 'EFISIENSI_OPTIMAL: Profil tanur RKEF memenuhi standar konsumsi energi spesifik industri logam hilir.'
            : 'BOROS_ENERGI: Tingginya kelembaban bijih umpan menyebabkan pemborosan daya kiln. Tingkatkan pre-drying rotary dryer.';

        return [
            'total_wet_feed_tons' => round($wetTons, 2),
            'water_evaporation_gj' => $evaporationGj,
            'dehydroxylation_gj' => $dehydroxylationGj,
            'coal_thermal_input_gj' => $coalGj,
            'electric_power_input_gj' => $electricGj,
            'total_energy_input_gj' => $totalInputGj,
            'specific_energy_consumption_gj_per_ton' => $secGjPerTon,
            'furnace_thermal_efficiency_pct' => $thermalEfficiency,
            'energy_performance_verdict' => $verdict,
        ];
    }
}
