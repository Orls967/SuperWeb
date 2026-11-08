<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

/**
 * Service Pasokan Listrik Darat Ramah Lingkungan (Onshore Power Supply / Cold Ironing).
 * Standar: IEC/IEEE 80005 High Voltage Shore Connection (HVSC).
 * Menghentikan mesin bantu (auxiliary engines) kapal selama sandar guna mengeliminasi emisi lokal pelabuhan.
 */
final class ColdIroningShorePowerService
{
    /**
     * Hitung pasokan listrik darat dan estimasi emisi yang dihindari di pelabuhan.
     *
     * @param  float  $stayHours  Durasi penyambungan listrik darat (jam)
     * @param  float  $averagePowerDemandKw  Kebutuhan daya listrik kapal (kW)
     * @param  float  $shoreTariffUsdPerKwh  Tarif listrik darat (USD/kWh)
     * @param  float  $mgoPricePerMt  Harga bahan bakar diesel kapal (USD/MT)
     * @return array{
     *     total_energy_consumed_kwh: float,
     *     electricity_cost_usd: float,
     *     avoided_mgo_fuel_mt: float,
     *     avoided_fuel_cost_usd: float,
     *     net_economic_benefit_usd: float,
     *     avoided_co2_emissions_mt: float,
     *     avoided_nox_emissions_kg: float,
     *     avoided_pm25_emissions_kg: float
     * }
     */
    public function calculateShorePowerAndAvoidedEmissions(
        float $stayHours,
        float $averagePowerDemandKw,
        float $shoreTariffUsdPerKwh = 0.165,
        float $mgoPriceRatePerTon = 750.0
    ): array {
        $totalKwh = $stayHours * $averagePowerDemandKw;
        $electricityCost = $totalKwh * $shoreTariffUsdPerKwh;

        // Jika menggunakan mesin bantu kapal (SFOC ~ 215 g/kWh MGO):
        $avoidedMgoMt = ($totalKwh * 215.0) / 1_000_000.0;
        $avoidedFuelCost = $avoidedMgoMt * $mgoPriceRatePerTon;
        $netBenefit = $avoidedFuelCost - $electricityCost;

        // Faktor emisi MARPOL Annex VI untuk genset MGO:
        // CO2: 3.206 t-CO2/t-MGO
        // NOx: 14.4 kg/t-MGO (IMO Tier II)
        // PM2.5: 0.85 kg/t-MGO
        $avoidedCo2Mt = $avoidedMgoMt * 3.206;
        $avoidedNoxKg = $avoidedMgoMt * 14.4;
        $avoidedPmKg = $avoidedMgoMt * 0.85;

        return [
            'total_energy_consumed_kwh' => round($totalKwh, 2),
            'electricity_cost_usd' => round($electricityCost, 2),
            'avoided_mgo_fuel_mt' => round($avoidedMgoMt, 3),
            'avoided_fuel_cost_usd' => round($avoidedFuelCost, 2),
            'net_economic_benefit_usd' => round($netBenefit, 2),
            'avoided_co2_emissions_mt' => round($avoidedCo2Mt, 3),
            'avoided_nox_emissions_kg' => round($avoidedNoxKg, 2),
            'avoided_pm25_emissions_kg' => round($avoidedPmKg, 2),
        ];
    }
}
