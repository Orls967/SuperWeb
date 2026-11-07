<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningVentilationShaft;

/**
 * Domain Action: Optimasi Konsumsi Daya Kipas Utama Ventilasi Tambang (VSD Optimization).
 * Menggunakan hukum afinitas kipas aerodinamika (Fan Affinity Laws) untuk menghitung
 * penurunan daya poros dan penghematan biaya listrik pada shift rendah personel.
 */
class OptimizeMainVentilationFanPowerAction
{
    /**
     * @param  array{
     *     shaft_id: string,
     *     target_airflow_m3_s: float,
     *     electricity_tariff_per_kwh?: float,
     *     motor_efficiency_pct?: float,
     *     hours_per_day_low_demand?: float
     * } $data
     * @return array{
     *     shaft_code: string,
     *     rated_airflow_m3_s: float,
     *     target_airflow_m3_s: float,
     *     vsd_speed_ratio: float,
     *     optimized_fan_power_kw: float,
     *     power_saving_kw: float,
     *     annual_kwh_saved: float,
     *     annual_cost_saved_idr: float,
     *     new_operating_pressure_pa: float
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $shaft = MiningVentilationShaft::query()->findOrFail($data['shaft_id']);

        $ratedFlow = (float) $shaft->fan_rated_airflow_m3_s;
        $ratedPower = (float) $shaft->fan_installed_power_kw;
        $ratedPressure = (float) $shaft->fan_operating_pressure_pa;

        if ($ratedFlow <= 0 || $ratedPower <= 0) {
            throw new InvalidArgumentException("Shaft {$shaft->shaft_code} tidak memiliki spesifikasi fan rated airflow / power yang valid.");
        }

        $targetFlow = (float) $data['target_airflow_m3_s'];
        if ($targetFlow > $ratedFlow * 1.15) {
            throw new InvalidArgumentException("Target airflow ({$targetFlow} m3/s) melampaui batas overspeed fan 115% ({$ratedFlow} m3/s).");
        }

        // Rasio kecepatan VSD: alpha = Q_target / Q_rated
        $speedRatio = min(1.0, max(0.40, $targetFlow / $ratedFlow));

        // Berdasarkan Fan Laws:
        // Pressure berbanding lurus kuadrat kecepatan: P2 = P1 * (N2 / N1)^2
        $newPressure = round($ratedPressure * ($speedRatio ** 2), 2);

        // Power berbanding lurus kubik kecepatan: W2 = W1 * (N2 / N1)^3
        $motorEff = (float) ($data['motor_efficiency_pct'] ?? 92.0) / 100.0;
        $shaftPower = $ratedPower * ($speedRatio ** 3);
        $optimizedElectricPowerKw = round($shaftPower / $motorEff, 2);

        $baseElectricPowerKw = round($ratedPower / $motorEff, 2);
        $powerSavingKw = round(max(0.0, $baseElectricPowerKw - $optimizedElectricPowerKw), 2);

        $hoursPerDay = (float) ($data['hours_per_day_low_demand'] ?? 8.0);
        $annualHours = $hoursPerDay * 365.0;
        $annualKwhSaved = round($powerSavingKw * $annualHours, 2);

        $tariff = (float) ($data['electricity_tariff_per_kwh'] ?? 1450.0); // IDR per kWh (Industri I-3/I-4)
        $annualCostSavedIdr = round($annualKwhSaved * $tariff, 2);

        // Update shaft metadata dengan rekomendasi setpoint VSD
        $meta = $shaft->metadata ?? [];
        $meta['vsd_energy_optimization'] = [
            'speed_ratio_pct' => round($speedRatio * 100, 1),
            'target_airflow_m3_s' => $targetFlow,
            'optimized_power_kw' => $optimizedElectricPowerKw,
            'power_saving_kw' => $powerSavingKw,
            'annual_cost_saved_idr' => $annualCostSavedIdr,
            'calculated_at' => now()->toIso8601String(),
        ];
        $shaft->metadata = $meta;
        $shaft->save();

        return [
            'shaft_code' => $shaft->shaft_code,
            'rated_airflow_m3_s' => $ratedFlow,
            'target_airflow_m3_s' => $targetFlow,
            'vsd_speed_ratio' => round($speedRatio, 4),
            'optimized_fan_power_kw' => $optimizedElectricPowerKw,
            'power_saving_kw' => $powerSavingKw,
            'annual_kwh_saved' => $annualKwhSaved,
            'annual_cost_saved_idr' => $annualCostSavedIdr,
            'new_operating_pressure_pa' => $newPressure,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['shaft_id'])) {
            throw new InvalidArgumentException('Shaft ID tidak boleh kosong.');
        }

        if (($data['target_airflow_m3_s'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Target airflow harus lebih besar dari 0 m3/s.');
        }
    }
}
