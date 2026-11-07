<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;

/**
 * Layanan Pemeliharaan Jalan Tambang (Haul Road Condition & Rolling Resistance).
 *
 * Mengoptimalkan jadwal unit pendukung (Motor Grader, Water Truck, Compactor)
 * untuk meminimalkan Rolling Resistance (RR) dan debu tambang, sehingga konsumsi solar efisien.
 */
final class HaulRoadFleetMaintenanceService
{
    /**
     * @param string $pitId
     * @param float $roadLengthKm Panjang segmen jalan tambang
     * @param float $currentRoughnessIri Indeks ketidakrataan jalan (International Roughness Index)
     * @param int $dustLevelPpm Tingkat konsentrasi debu particulate matter (PM10)
     * @param int $graderCount Jumlah motor grader aktif
     * @param int $waterTruckCount Jumlah water truck aktif
     * @return array{
     *     road_condition_score: string,
     *     rolling_resistance_pct: float,
     *     fuel_burn_penalty_pct: float,
     *     max_safe_speed_kmh: int,
     *     scheduled_actions: list<string>,
     *     estimated_maintenance_cost_idr: int
     * }
     */
    public function evaluateHaulRoad(
        string $pitId,
        float $roadLengthKm,
        float $currentRoughnessIri,
        int $dustLevelPpm,
        int $graderCount = 1,
        int $waterTruckCount = 2,
    ): array {
        if ($roadLengthKm <= 0.0) {
            throw new InvalidArgumentException('Panjang segmen jalan tambang harus lebih dari 0 km.');
        }

        // Rolling resistance dasar jalan tambang terawat baik = 2.0%
        // Setiap kenaikan IRI menambah resistansi gelinding
        $baseRollingResistance = 2.0;
        $roughnessPenalty = max(0.0, ($currentRoughnessIri - 2.5) * 0.8);
        $rollingResistance = round($baseRollingResistance + $roughnessPenalty, 2);

        // Penalti konsumsi BBM: setiap kenaikan 1% RR menaikkan konsumsi BBM sekitar 10%
        $fuelBurnPenaltyPct = round(max(0.0, ($rollingResistance - 2.0) * 10.0), 1);

        // Batas kecepatan aman truk berdasarkan kondisi jalan
        $maxSpeed = 40;
        if ($rollingResistance > 4.5 || $currentRoughnessIri > 5.0) {
            $maxSpeed = 25;
        } elseif ($rollingResistance > 3.0) {
            $maxSpeed = 32;
        }

        $scheduledActions = [];
        $costEstimate = 0;

        // Evaluasi kebutuhan grading
        if ($currentRoughnessIri >= 3.5) {
            $scheduledActions[] = "Penjadwalan Motor Grader untuk segmen {$roadLengthKm} km (IRI: {$currentRoughnessIri})";
            $costEstimate += (int) round($roadLengthKm * 1500000); // Rp 1.5jt per km grading
        }

        // Evaluasi kebutuhan penyiraman jalan tambang (Water Truck)
        if ($dustLevelPpm >= 150) {
            $scheduledActions[] = "Penyiraman Water Truck intensif untuk menekan konsentrasi debu ({$dustLevelPpm} ppm > ambang 150 ppm)";
            $costEstimate += (int) round($roadLengthKm * 800000); // Rp 800rb per km penyiraman
        }

        if (count($scheduledActions) === 0) {
            $scheduledActions[] = 'Jalan tambang dalam kondisi prima, pemantauan berkala tetap berjalan';
            $conditionScore = 'EXCELLENT';
        } elseif (count($scheduledActions) === 1) {
            $conditionScore = 'ACCEPTABLE_MAINTENANCE_REQUIRED';
        } else {
            $conditionScore = 'POOR_IMMEDIATE_ACTION_REQUIRED';
        }

        return [
            'road_condition_score' => $conditionScore,
            'rolling_resistance_pct' => $rollingResistance,
            'fuel_burn_penalty_pct' => $fuelBurnPenaltyPct,
            'max_safe_speed_kmh' => $maxSpeed,
            'scheduled_actions' => $scheduledActions,
            'estimated_maintenance_cost_idr' => $costEstimate,
        ];
    }

    /**
     * Menghitung total konsumsi bahan bakar tambahan akibat kondisi jalan tambang buruk di seluruh siklus angkut shift.
     *
     * @param float $rollingResistancePct
     * @param int $totalHaulCycles
     * @param float $averagePayloadTon
     * @param float $haulDistanceKm
     * @return array{
     *     additional_fuel_liters: float,
     *     additional_fuel_cost_idr: int,
     *     co2e_penalty_kg: float
     * }
     */
    public function computeShiftFleetPenalty(
        float $rollingResistancePct,
        int $totalHaulCycles,
        float $averagePayloadTon,
        float $haulDistanceKm,
    ): array {
        // Konsumsi standar: 0.15 Liter solar per ton-km pada RR standar 2.0%
        $totalTonKm = $totalHaulCycles * $averagePayloadTon * $haulDistanceKm;
        $standardFuelLiters = $totalTonKm * 0.15;

        $rrExcess = max(0.0, $rollingResistancePct - 2.0);
        $penaltyFactor = $rrExcess * 0.10; // Tiap 1% RR berlebih menaikkan 10% solar

        $additionalLiters = round($standardFuelLiters * $penaltyFactor, 2);
        $dieselPricePerLiter = 15000;
        $additionalCostIdr = (int) round($additionalLiters * $dieselPricePerLiter);

        // Faktor emisi solar: 2.68 kg CO2e per liter solar
        $co2ePenaltyKg = round($additionalLiters * 2.68, 2);

        return [
            'additional_fuel_liters' => $additionalLiters,
            'additional_fuel_cost_idr' => $additionalCostIdr,
            'co2e_penalty_kg' => $co2ePenaltyKg,
        ];
    }
}
