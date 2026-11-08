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

    /**
     * Hitung analisis kelonggaran waktu (Slack Time Buffer) per tahapan ground handling.
     *
     * @param  array<int, array{milestone: string, start_min: int, end_min: int, critical: bool}>  $milestones
     * @return array<int, array{milestone: string, duration_minutes: int, slack_buffer_minutes: int, risk_level: string}>
     */
    public function calculateBufferSlackAnalysis(array $milestones, int $targetTurnaroundTotalMinutes): array
    {
        $analysis = [];

        foreach ($milestones as $m) {
            $duration = max(1, $m['end_min'] - $m['start_min']);
            $slack = max(0, $targetTurnaroundTotalMinutes - $m['end_min']);

            $risk = 'LOW';
            if ($m['critical']) {
                $risk = $slack < 10 ? 'CRITICAL_HIGH' : 'MODERATE';
            } elseif ($slack < 5) {
                $risk = 'MODERATE';
            }

            $analysis[] = [
                'milestone' => $m['milestone'],
                'duration_minutes' => $duration,
                'slack_buffer_minutes' => $slack,
                'risk_level' => $risk,
            ];
        }

        return $analysis;
    }

    /**
     * Evaluasi kontensi peralatan darat (Ground Support Equipment Contention).
     *
     * @param  array<string, int>  $availableGse
     * @param  array<string, int>  $requiredGse
     * @return array{has_contention: bool, deficit_items: array<string, int>, delay_estimate_minutes: int}
     */
    public function evaluateGseResourceContention(array $availableGse, array $requiredGse): array
    {
        $deficits = [];
        $maxDelay = 0;

        foreach ($requiredGse as $type => $qty) {
            $avail = $availableGse[$type] ?? 0;
            if ($avail < $qty) {
                $diff = $qty - $avail;
                $deficits[$type] = $diff;
                // Setiap kekurangan GPU atau Tug berpotensi menimbulkan delay 15-25 menit
                $unitDelay = in_array($type, ['tug', 'gpu', 'air_starter'], true) ? 20 : 10;
                $maxDelay = max($maxDelay, $diff * $unitDelay);
            }
        }

        return [
            'has_contention' => count($deficits) > 0,
            'deficit_items' => $deficits,
            'delay_estimate_minutes' => $maxDelay,
        ];
    }
}
