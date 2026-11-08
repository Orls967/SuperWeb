<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Algorithms;

/**
 * Algoritma: Airport Slot Congestion Analyzer.
 *
 * Menganalisis kurva kepadatan pergerakan pesawat per jam (hourly runway movement),
 * mendeteksi puncak kemacetan (peak congestion hours), dan memperkirakan risiko
 * keterlambatan berantai (delay propagation index).
 */
class AirportSlotCongestionAnalyzer
{
    /**
     * Evaluasi tingkat kemacetan landasan pacu bandara per jendela waktu 60 menit.
     *
     * @param  int  $hourlyRunwayCapacity  Kapasitas maksimum pergerakan landasan per jam (e.g. 72 di CGK)
     * @param  array<int, array{flight_number: string, scheduled_time: string, operation_type: string}>  $scheduledMovements
     * @return array{
     *     total_movements: int,
     *     peak_hour: string,
     *     peak_movement_count: int,
     *     capacity_utilization_peak_percentage: float,
     *     congested_hours_count: int,
     *     delay_propagation_risk: string
     * }
     */
    public function analyzeHourlyCongestion(int $hourlyRunwayCapacity, array $scheduledMovements): array
    {
        $cap = max(1, $hourlyRunwayCapacity);
        $hourlyDistribution = [];

        foreach ($scheduledMovements as $mov) {
            $hourKey = substr($mov['scheduled_time'], 0, 13).':00'; // Format: YYYY-MM-DD HH:00
            $hourlyDistribution[$hourKey] = ($hourlyDistribution[$hourKey] ?? 0) + 1;
        }

        if (empty($hourlyDistribution)) {
            return [
                'total_movements' => 0,
                'peak_hour' => 'N/A',
                'peak_movement_count' => 0,
                'capacity_utilization_peak_percentage' => 0.0,
                'congested_hours_count' => 0,
                'delay_propagation_risk' => 'LOW',
            ];
        }

        arsort($hourlyDistribution);
        $peakHour = (string) array_key_first($hourlyDistribution);
        $peakCount = (int) reset($hourlyDistribution);

        $congestedHours = 0;
        foreach ($hourlyDistribution as $count) {
            if ($count >= (int) floor($cap * 0.90)) { // 90% atau lebih dari kapasitas
                $congestedHours++;
            }
        }

        $peakUtilization = ($peakCount / (float) $cap) * 100.0;

        $risk = match (true) {
            $peakUtilization > 100.0 => 'CRITICAL_GRIDLOCK',
            $peakUtilization >= 90.0 => 'HIGH_DELAY_RISK',
            $peakUtilization >= 75.0 => 'MODERATE',
            default => 'LOW',
        };

        return [
            'total_movements' => count($scheduledMovements),
            'peak_hour' => $peakHour,
            'peak_movement_count' => $peakCount,
            'capacity_utilization_peak_percentage' => round($peakUtilization, 2),
            'congested_hours_count' => $congestedHours,
            'delay_propagation_risk' => $risk,
        ];
    }
}
