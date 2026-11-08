<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Services;

class AviationAircraftTurnaroundTimeOptimizerService
{
    /**
     * Susun jalur kritis proses perputaran darat pesawat (Critical Path Turnaround Schedule).
     *
     * @param  string  $aircraftSize  'NARROWBODY' atau 'WIDEBODY'
     * @return array{
     *     target_turnaround_minutes: int,
     *     critical_path_milestones: array<int, array{milestone: string, start_min: int, end_min: int, critical: bool}>,
     *     bottleneck_process: string
     * }
     */
    public function calculateCriticalTurnaroundPath(
        string $aircraftSize,
        int $incomingPaxCount,
        int $outgoingPaxCount,
        bool $refuelingRequired = true
    ): array {
        $isWidebody = strtoupper($aircraftSize) === 'WIDEBODY';

        // Durasi proses dasar (menit)
        $deplaneMin = (int) ceil($incomingPaxCount / ($isWidebody ? 18.0 : 12.0));
        $refuelMin = $refuelingRequired ? ($isWidebody ? 30 : 18) : 0;
        $cleaningMin = $isWidebody ? 25 : 15;
        $cateringMin = $isWidebody ? 20 : 12;
        $boardMin = (int) ceil($outgoingPaxCount / ($isWidebody ? 14.0 : 9.0));

        // Milestones
        $m1 = ['milestone' => 'Deboarding Passengers', 'start_min' => 0, 'end_min' => $deplaneMin, 'critical' => true];
        $m2 = ['milestone' => 'Refueling Jet A-1', 'start_min' => 5, 'end_min' => 5 + $refuelMin, 'critical' => $refuelMin > 20];
        $m3 = ['milestone' => 'Cabin Cleaning & Security Sweep', 'start_min' => $deplaneMin, 'end_min' => $deplaneMin + $cleaningMin, 'critical' => true];
        $m4 = ['milestone' => 'Galley Catering Restock', 'start_min' => $deplaneMin + 2, 'end_min' => $deplaneMin + 2 + $cateringMin, 'critical' => false];

        $boardingStart = max($deplaneMin + $cleaningMin, 5 + $refuelMin);
        $m5 = ['milestone' => 'Boarding Departing Passengers', 'start_min' => $boardingStart, 'end_min' => $boardingStart + $boardMin, 'critical' => true];
        $m6 = ['milestone' => 'Door Closure & Pushback Tug Coupling', 'start_min' => $boardingStart + $boardMin, 'end_min' => $boardingStart + $boardMin + 5, 'critical' => true];

        $totalMin = $boardingStart + $boardMin + 5;

        return [
            'target_turnaround_minutes' => $totalMin,
            'critical_path_milestones' => [$m1, $m2, $m3, $m4, $m5, $m6],
            'bottleneck_process' => 'Cabin Cleaning & Boarding Sequence',
        ];
    }
}
