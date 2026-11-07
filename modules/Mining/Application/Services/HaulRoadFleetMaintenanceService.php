<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningPit;

/**
 * Layanan Evaluasi Kondisi Jalan Angkut Tambang (Haul Road Rolling Resistance & Dust Suppression)
 * dalam satuan integer (meter, bps, ppm, ml, rupiah) (D93-2, D93-5).
 */
final class HaulRoadFleetMaintenanceService
{
    private const BASE_ROLLING_RESISTANCE_BPS = 200; // 2.00%

    private const BASE_IRI_MM_PER_M = 2500; // 2.50 m/km

    private const GRADING_COST_PER_KM_IDR = 1500000;

    private const WATERING_COST_PER_KM_IDR = 800000;

    /**
     * @param  string  $pitId
     * @param  int  $roadLengthMeters  Panjang segmen jalan angkut (meter)
     * @param  int  $roughnessIriMmPerKm  Indeks ketidakrataan permukaan jalan (mm/km, misal 3200 = 3.2 m/km)
     * @param  int  $dustConcentrationPpm  Konsentrasi partikulat debu PM10 (ppm/ug-m3)
     * @return array{
     *     pit_code: string,
     *     road_condition_score: string,
     *     rolling_resistance_bps: int,
     *     fuel_burn_penalty_bps: int,
     *     max_safe_speed_kmh: int,
     *     scheduled_actions: list<string>,
     *     estimated_maintenance_cost_idr: int
     * }
     */
    public function evaluateHaulRoad(
        string $pitId,
        int $roadLengthMeters,
        int $roughnessIriMmPerKm,
        int $dustConcentrationPpm,
    ): array {
        if ($roadLengthMeters <= 0) {
            throw new InvalidArgumentException('Panjang segmen jalan tambang harus lebih besar dari 0 meter.');
        }

        $pit = MiningPit::find($pitId);
        $pitCode = $pit ? (string) $pit->pit_code : $pitId;

        // Setiap kenaikan 1000 mm/km di atas 2500 menambah 80 bps (0.80%) rolling resistance
        $excessIri = max(0, $roughnessIriMmPerKm - self::BASE_IRI_MM_PER_M);
        $roughnessPenaltyBps = intdiv($excessIri * 80, 1000);
        $rollingResistanceBps = self::BASE_ROLLING_RESISTANCE_BPS + $roughnessPenaltyBps;

        // Setiap 100 bps (1%) kenaikan RR menaikkan konsumsi solar sebesar 1000 bps (10%)
        $fuelBurnPenaltyBps = ($rollingResistanceBps - self::BASE_ROLLING_RESISTANCE_BPS) * 10;

        $maxSpeedKmh = 40;
        if ($rollingResistanceBps > 450 || $roughnessIriMmPerKm > 5000) {
            $maxSpeedKmh = 25;
        } elseif ($rollingResistanceBps > 300) {
            $maxSpeedKmh = 32;
        }

        $scheduledActions = [];
        $costEstimateIdr = 0;

        if ($roughnessIriMmPerKm >= 3500) {
            $scheduledActions[] = "Kerahkan Motor Grader pada koridor {$pitCode} sepanjang {$roadLengthMeters} m (IRI {$roughnessIriMmPerKm} mm/km)";
            $costEstimateIdr += intdiv($roadLengthMeters * self::GRADING_COST_PER_KM_IDR, 1000);
        }

        if ($dustConcentrationPpm >= 150) {
            $scheduledActions[] = "Jadwalkan penyiraman Water Truck pada koridor {$pitCode} (Debu {$dustConcentrationPpm} ppm >= 150 ppm)";
            $costEstimateIdr += intdiv($roadLengthMeters * self::WATERING_COST_PER_KM_IDR, 1000);
        }

        if (count($scheduledActions) === 0) {
            $scheduledActions[] = "Koridor angkut {$pitCode} memenuhi standar resistansi gelinding optimal";
            $conditionScore = 'EXCELLENT';
        } elseif (count($scheduledActions) === 1) {
            $conditionScore = 'MAINTENANCE_REQUIRED';
        } else {
            $conditionScore = 'IMMEDIATE_GRADING_AND_WATERING';
        }

        return [
            'pit_code' => $pitCode,
            'road_condition_score' => $conditionScore,
            'rolling_resistance_bps' => $rollingResistanceBps,
            'fuel_burn_penalty_bps' => $fuelBurnPenaltyBps,
            'max_safe_speed_kmh' => $maxSpeedKmh,
            'scheduled_actions' => $scheduledActions,
            'estimated_maintenance_cost_idr' => $costEstimateIdr,
        ];
    }

    /**
     * Menghitung penalti pemakaian solar tambahan pada shift akibat resistansi gelinding di atas standar.
     *
     * @return array{
     *     standard_fuel_ml: int,
     *     additional_fuel_ml: int,
     *     additional_fuel_cost_idr: int,
     *     co2e_penalty_grams: int
     * }
     */
    public function computeShiftFleetPenalty(
        int $rollingResistanceBps,
        int $totalHaulCycles,
        int $averagePayloadKg,
        int $haulDistanceMeters,
        int $dieselPriceIdrPerLiter = 15000,
    ): array {
        $payloadTon = intdiv(max(0, $averagePayloadKg), 1000);
        $totalTonKm = intdiv(max(0, $totalHaulCycles) * $payloadTon * max(0, $haulDistanceMeters), 1000);

        // Standar: 150 ml solar per ton-km pada RR 200 bps (2.0%)
        $standardFuelMl = $totalTonKm * 150;
        $excessRrBps = max(0, $rollingResistanceBps - self::BASE_ROLLING_RESISTANCE_BPS);
        $penaltyBps = $excessRrBps * 10;

        $additionalFuelMl = intdiv($standardFuelMl * $penaltyBps, 10000);
        $additionalCostIdr = intdiv($additionalFuelMl * $dieselPriceIdrPerLiter, 1000);

        // Faktor emisi solar: 2680 gram CO2e per liter (2.68 gram per ml)
        $co2ePenaltyGrams = intdiv($additionalFuelMl * 2680, 1000);

        return [
            'standard_fuel_ml' => $standardFuelMl,
            'additional_fuel_ml' => $additionalFuelMl,
            'additional_fuel_cost_idr' => $additionalCostIdr,
            'co2e_penalty_grams' => $co2ePenaltyGrams,
        ];
    }
}
