<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use Carbon\CarbonInterface;
use InvalidArgumentException;
use Modules\Wms\Domain\Models\DockAppointment;

/**
 * Layanan penjadwalan & optimasi dermaga (inbound/outbound/cross_dock):
 * deteksi bentrok waktu dock_door, skor SLA turnaround (bps), risiko carrier cutoff,
 * dan rekomendasi pemindahan ke dermaga cadangan terdekat.
 */
final class DockSchedulingOptimizationService
{
    public const DEFAULT_DOORS = ['D-01', 'D-02', 'D-03', 'D-04'];

    public const MINUTES_PER_DAY = 1440;

    public const DEFAULT_PALLET_COUNT = 10;

    public function normalizeDirection(string $direction): string
    {
        return match (strtolower(trim($direction))) {
            'in', 'inbound' => 'inbound',
            'out', 'outbound' => 'outbound',
            'cross_dock', 'cross-dock', 'crossdock' => 'cross_dock',
            default => throw new InvalidArgumentException("Arah dermaga tidak valid: {$direction}"),
        };
    }

    /**
     * Mengevaluasi seluruh jadwal dermaga, mendeteksi bentrok, menghitung skor SLA,
     * menilai risiko carrier cutoff, dan menyusun rekomendasi relokasi dock cadangan.
     *
     * @param  list<array{
     *     appointment_id: int|string,
     *     dock_door: string,
     *     direction: string,
     *     window_start_min: int,
     *     window_end_min: int,
     *     carrier?: string|null,
     *     cutoff_min?: int|null,
     *     pallet_count?: int,
     *     status?: string
     * }>  $appointments
     * @param  list<string>  $availableDoors
     * @return array{
     *     total_appointments: int,
     *     active_appointments: int,
     *     conflict_count: int,
     *     average_sla_score_bps: int,
     *     high_risk_count: int,
     *     conflicts: list<array{
     *         dock_door: string,
     *         appointment_a: int|string,
     *         appointment_b: int|string,
     *         overlap_minutes: int,
     *         recommended_door_for_b: string|null,
     *         recommended_window_start_min: int|null,
     *         recommended_window_end_min: int|null
     *     }>,
     *     assessments: list<array{
     *         appointment_id: int|string,
     *         dock_door: string,
     *         direction: string,
     *         window_start_min: int,
     *         window_end_min: int,
     *         expected_turnaround_min: int,
     *         has_conflict: bool,
     *         sla_score_bps: int,
     *         cutoff_margin_min: int|null,
     *         cutoff_risk: string,
     *         recommended_door: string|null
     *     }>
     * }
     */
    public function evaluateSchedule(array $appointments, array $availableDoors = self::DEFAULT_DOORS): array
    {
        if ($appointments === []) {
            return [
                'total_appointments' => 0,
                'active_appointments' => 0,
                'conflict_count' => 0,
                'average_sla_score_bps' => 10000,
                'high_risk_count' => 0,
                'conflicts' => [],
                'assessments' => [],
            ];
        }

        $doors = $availableDoors !== [] ? array_values(array_unique($availableDoors)) : self::DEFAULT_DOORS;
        $normalized = $this->normalizeAppointments($appointments);

        $active = array_values(array_filter(
            $normalized,
            static fn (array $item): bool => $item['status'] !== 'cancelled'
        ));

        $conflicts = [];
        $conflictedIds = [];
        $recommendedDoorById = [];

        $count = count($active);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $active[$i];
                $b = $active[$j];

                if ($a['dock_door'] !== $b['dock_door']) {
                    continue;
                }

                $overlapMinutes = $this->calculateOverlapMinutes(
                    $a['window_start_min'],
                    $a['window_end_min'],
                    $b['window_start_min'],
                    $b['window_end_min']
                );

                if ($overlapMinutes <= 0) {
                    continue;
                }

                $conflictedIds[(string) $a['appointment_id']] = true;
                $conflictedIds[(string) $b['appointment_id']] = true;

                $relocation = $this->findNearestAvailableDoor($b, $active, $doors);
                $recommendedDoorById[(string) $b['appointment_id']] = $relocation['door'];

                $conflicts[] = [
                    'dock_door' => $a['dock_door'],
                    'appointment_a' => $a['appointment_id'],
                    'appointment_b' => $b['appointment_id'],
                    'overlap_minutes' => $overlapMinutes,
                    'recommended_door_for_b' => $relocation['door'],
                    'recommended_window_start_min' => $relocation['window_start_min'],
                    'recommended_window_end_min' => $relocation['window_end_min'],
                ];
            }
        }

        $assessments = [];
        $totalSlaScore = 0;
        $highRiskCount = 0;

        foreach ($active as $item) {
            $idKey = (string) $item['appointment_id'];
            $hasConflict = isset($conflictedIds[$idKey]);
            $expectedTurnaround = $this->estimateTurnaroundMinutes($item['direction'], $item['pallet_count']);
            $allocatedMinutes = $item['window_end_min'] - $item['window_start_min'];
            $cutoffMargin = $item['cutoff_min'] !== null ? ($item['cutoff_min'] - $item['window_end_min']) : null;

            $slaScoreBps = $this->computeSlaScoreBps(
                allocatedMinutes: $allocatedMinutes,
                expectedTurnaround: $expectedTurnaround,
                hasConflict: $hasConflict,
                cutoffMargin: $cutoffMargin,
                direction: $item['direction'],
            );

            $cutoffRisk = $this->classifyCutoffRisk(
                cutoffMargin: $cutoffMargin,
                hasConflict: $hasConflict,
                allocatedMinutes: $allocatedMinutes,
                expectedTurnaround: $expectedTurnaround,
                direction: $item['direction'],
            );

            if (in_array($cutoffRisk, ['HIGH', 'CRITICAL'], true)) {
                $highRiskCount++;
            }

            $totalSlaScore += $slaScoreBps;
            $assessments[] = [
                'appointment_id' => $item['appointment_id'],
                'dock_door' => $item['dock_door'],
                'direction' => $item['direction'],
                'window_start_min' => $item['window_start_min'],
                'window_end_min' => $item['window_end_min'],
                'expected_turnaround_min' => $expectedTurnaround,
                'has_conflict' => $hasConflict,
                'sla_score_bps' => $slaScoreBps,
                'cutoff_margin_min' => $cutoffMargin,
                'cutoff_risk' => $cutoffRisk,
                'recommended_door' => $recommendedDoorById[$idKey] ?? null,
            ];
        }

        $activeCount = count($active);

        return [
            'total_appointments' => count($appointments),
            'active_appointments' => $activeCount,
            'conflict_count' => count($conflicts),
            'average_sla_score_bps' => $activeCount > 0 ? intdiv($totalSlaScore, $activeCount) : 10000,
            'high_risk_count' => $highRiskCount,
            'conflicts' => $conflicts,
            'assessments' => $assessments,
        ];
    }

    /**
     * Menjadwalkan satu kandidat jendela dermaga dengan cek bentrok dan rekomendasi alternatif.
     *
     * @param  array{
     *     appointment_id?: int|string,
     *     dock_door: string,
     *     direction: string,
     *     window_start_min: int,
     *     window_end_min: int,
     *     carrier?: string|null,
     *     cutoff_min?: int|null,
     *     pallet_count?: int
     * }  $candidate
     * @param  list<array<string, mixed>>  $existingAppointments
     * @param  list<string>  $availableDoors
     * @return array{
     *     scheduled: bool,
     *     assigned_door: string,
     *     alternative_door: string|null,
     *     recommended_window_start_min: int|null,
     *     recommended_window_end_min: int|null,
     *     sla_score_bps: int,
     *     cutoff_risk: string,
     *     conflict_with: list<int|string>
     * }
     */
    public function scheduleWindow(
        array $candidate,
        array $existingAppointments,
        array $availableDoors = self::DEFAULT_DOORS,
    ): array {
        $doors = $availableDoors !== [] ? array_values(array_unique($availableDoors)) : self::DEFAULT_DOORS;
        $normalizedCandidate = $this->normalizeSingle([
            ...$candidate,
            'appointment_id' => $candidate['appointment_id'] ?? 'CANDIDATE',
            'status' => 'scheduled',
        ]);
        $normalizedExisting = array_values(array_filter(
            $this->normalizeAppointments($existingAppointments),
            static fn (array $item): bool => $item['status'] !== 'cancelled'
        ));

        $conflictWith = [];
        foreach ($normalizedExisting as $existing) {
            if ($existing['dock_door'] === $normalizedCandidate['dock_door']
                && $this->calculateOverlapMinutes(
                    $normalizedCandidate['window_start_min'],
                    $normalizedCandidate['window_end_min'],
                    $existing['window_start_min'],
                    $existing['window_end_min']
                ) > 0) {
                $conflictWith[] = $existing['appointment_id'];
            }
        }

        $hasConflict = $conflictWith !== [];
        $alternative = $hasConflict
            ? $this->findNearestAvailableDoor($normalizedCandidate, $normalizedExisting, $doors)
            : ['door' => null, 'window_start_min' => null, 'window_end_min' => null];

        $expectedTurnaround = $this->estimateTurnaroundMinutes(
            $normalizedCandidate['direction'],
            $normalizedCandidate['pallet_count']
        );
        $allocatedMinutes = $normalizedCandidate['window_end_min'] - $normalizedCandidate['window_start_min'];
        $cutoffMargin = $normalizedCandidate['cutoff_min'] !== null
            ? ($normalizedCandidate['cutoff_min'] - $normalizedCandidate['window_end_min'])
            : null;

        return [
            'scheduled' => ! $hasConflict,
            'assigned_door' => $hasConflict && $alternative['door'] !== null
                ? $alternative['door']
                : $normalizedCandidate['dock_door'],
            'alternative_door' => $alternative['door'],
            'recommended_window_start_min' => $alternative['window_start_min'],
            'recommended_window_end_min' => $alternative['window_end_min'],
            'sla_score_bps' => $this->computeSlaScoreBps(
                allocatedMinutes: $allocatedMinutes,
                expectedTurnaround: $expectedTurnaround,
                hasConflict: $hasConflict,
                cutoffMargin: $cutoffMargin,
                direction: $normalizedCandidate['direction'],
            ),
            'cutoff_risk' => $this->classifyCutoffRisk(
                cutoffMargin: $cutoffMargin,
                hasConflict: $hasConflict,
                allocatedMinutes: $allocatedMinutes,
                expectedTurnaround: $expectedTurnaround,
                direction: $normalizedCandidate['direction'],
            ),
            'conflict_with' => $conflictWith,
        ];
    }

    /**
     * Mengadaptasi koleksi model DockAppointment ke evaluasi penjadwalan dermaga.
     *
     * @param  iterable<DockAppointment>  $appointments
     * @param  list<string>  $availableDoors
     * @return array<string, mixed>
     */
    public function evaluateFromEloquentAppointments(
        iterable $appointments,
        array $availableDoors = self::DEFAULT_DOORS,
    ): array {
        $slots = [];
        $doors = $availableDoors !== [] ? $availableDoors : self::DEFAULT_DOORS;

        foreach ($appointments as $idx => $dock) {
            $start = $dock->window_start;
            $end = $dock->window_end;

            $startMin = $start instanceof CarbonInterface
                ? ($start->hour * 60) + $start->minute
                : 0;
            $endMin = $end instanceof CarbonInterface
                ? ($end->hour * 60) + $end->minute
                : ($startMin + 60);

            if ($endMin <= $startMin) {
                $endMin = min(self::MINUTES_PER_DAY, $startMin + 60);
            }

            $dockDoor = (string) ($dock->getAttribute('dock_door') ?: $doors[$idx % count($doors)]);

            $slots[] = [
                'appointment_id' => $dock->id,
                'dock_door' => $dockDoor,
                'direction' => (string) $dock->direction,
                'window_start_min' => $startMin,
                'window_end_min' => $endMin,
                'carrier' => $dock->carrier,
                'cutoff_min' => $dock->direction === 'out' ? min(self::MINUTES_PER_DAY, $endMin + 60) : null,
                'pallet_count' => self::DEFAULT_PALLET_COUNT,
                'status' => (string) ($dock->status ?? 'scheduled'),
            ];
        }

        return $this->evaluateSchedule($slots, $doors);
    }

    public function estimateTurnaroundMinutes(string $direction, int $palletCount): int
    {
        $pallets = max(1, $palletCount);

        return match ($this->normalizeDirection($direction)) {
            'cross_dock' => 20 + ($pallets * 2),
            'outbound' => 30 + ($pallets * 3),
            default => 35 + ($pallets * 3),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $appointments
     * @return list<array{
     *     appointment_id: int|string,
     *     dock_door: string,
     *     direction: string,
     *     window_start_min: int,
     *     window_end_min: int,
     *     carrier: string|null,
     *     cutoff_min: int|null,
     *     pallet_count: int,
     *     status: string
     * }>
     */
    private function normalizeAppointments(array $appointments): array
    {
        $normalized = array_map(fn (array $item): array => $this->normalizeSingle($item), $appointments);

        usort($normalized, static fn (array $a, array $b): int => [
            $a['window_start_min'],
            $a['dock_door'],
            (string) $a['appointment_id'],
        ] <=> [
            $b['window_start_min'],
            $b['dock_door'],
            (string) $b['appointment_id'],
        ]);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     appointment_id: int|string,
     *     dock_door: string,
     *     direction: string,
     *     window_start_min: int,
     *     window_end_min: int,
     *     carrier: string|null,
     *     cutoff_min: int|null,
     *     pallet_count: int,
     *     status: string
     * }
     */
    private function normalizeSingle(array $item): array
    {
        $dockDoor = trim((string) $item['dock_door']);
        $startMin = (int) $item['window_start_min'];
        $endMin = (int) $item['window_end_min'];

        if ($dockDoor === '' || $startMin < 0 || $endMin > self::MINUTES_PER_DAY || $endMin <= $startMin) {
            throw new InvalidArgumentException("Jendela waktu dermaga tidak valid untuk {$dockDoor}.");
        }

        return [
            'appointment_id' => $item['appointment_id'],
            'dock_door' => $dockDoor,
            'direction' => $this->normalizeDirection((string) $item['direction']),
            'window_start_min' => $startMin,
            'window_end_min' => $endMin,
            'carrier' => isset($item['carrier']) ? (string) $item['carrier'] : null,
            'cutoff_min' => isset($item['cutoff_min']) ? (int) $item['cutoff_min'] : null,
            'pallet_count' => max(1, (int) ($item['pallet_count'] ?? self::DEFAULT_PALLET_COUNT)),
            'status' => strtolower(trim((string) ($item['status'] ?? 'scheduled'))),
        ];
    }

    private function calculateOverlapMinutes(int $startA, int $endA, int $startB, int $endB): int
    {
        $overlapStart = max($startA, $startB);
        $overlapEnd = min($endA, $endB);

        return max(0, $overlapEnd - $overlapStart);
    }

    /**
     * @param  array{appointment_id: int|string, dock_door: string, window_start_min: int, window_end_min: int}  $target
     * @param  list<array{appointment_id: int|string, dock_door: string, window_start_min: int, window_end_min: int}>  $active
     * @param  list<string>  $doors
     * @return array{door: string|null, window_start_min: int|null, window_end_min: int|null}
     */
    private function findNearestAvailableDoor(array $target, array $active, array $doors): array
    {
        $candidateDoors = array_values(array_filter(
            $doors,
            static fn (string $door): bool => $door !== $target['dock_door']
        ));

        $targetDoorNum = $this->extractDoorNumber($target['dock_door']);
        usort($candidateDoors, function (string $a, string $b) use ($targetDoorNum): int {
            $distA = abs($this->extractDoorNumber($a) - $targetDoorNum);
            $distB = abs($this->extractDoorNumber($b) - $targetDoorNum);

            return [$distA, $a] <=> [$distB, $b];
        });

        foreach ($candidateDoors as $door) {
            if (! $this->doorHasOverlap($door, $target['window_start_min'], $target['window_end_min'], $active, $target['appointment_id'])) {
                return [
                    'door' => $door,
                    'window_start_min' => $target['window_start_min'],
                    'window_end_min' => $target['window_end_min'],
                ];
            }
        }

        // Jika semua pintu sibuk di jam persis sama, cari slot paling awal di pintu terdekat
        $duration = $target['window_end_min'] - $target['window_start_min'];
        $searchDoors = array_merge($candidateDoors, [$target['dock_door']]);
        $bestOption = null;

        foreach ($searchDoors as $door) {
            $earliestEnd = $target['window_end_min'];
            foreach ($active as $slot) {
                if ($slot['dock_door'] === $door && $slot['appointment_id'] !== $target['appointment_id']) {
                    if ($slot['window_end_min'] > $target['window_start_min']) {
                        $earliestEnd = max($earliestEnd, $slot['window_end_min']);
                    }
                }
            }

            $shiftedStart = $earliestEnd;
            $shiftedEnd = $shiftedStart + $duration;
            if ($shiftedEnd <= self::MINUTES_PER_DAY
                && ! $this->doorHasOverlap($door, $shiftedStart, $shiftedEnd, $active, $target['appointment_id'])) {
                if ($bestOption === null || $shiftedStart < $bestOption['window_start_min']) {
                    $bestOption = [
                        'door' => $door,
                        'window_start_min' => $shiftedStart,
                        'window_end_min' => $shiftedEnd,
                    ];
                }
            }
        }

        return $bestOption ?? ['door' => null, 'window_start_min' => null, 'window_end_min' => null];
    }

    /**
     * @param  list<array{appointment_id: int|string, dock_door: string, window_start_min: int, window_end_min: int}>  $active
     */
    private function doorHasOverlap(
        string $door,
        int $startMin,
        int $endMin,
        array $active,
        int|string $ignoreAppointmentId,
    ): bool {
        foreach ($active as $slot) {
            if ($slot['appointment_id'] === $ignoreAppointmentId || $slot['dock_door'] !== $door) {
                continue;
            }
            if ($this->calculateOverlapMinutes($startMin, $endMin, $slot['window_start_min'], $slot['window_end_min']) > 0) {
                return true;
            }
        }

        return false;
    }

    private function extractDoorNumber(string $doorCode): int
    {
        preg_match('/(\d+)/', $doorCode, $matches);

        return isset($matches[1]) ? (int) $matches[1] : 99;
    }

    private function computeSlaScoreBps(
        int $allocatedMinutes,
        int $expectedTurnaround,
        bool $hasConflict,
        ?int $cutoffMargin,
        string $direction,
    ): int {
        $score = 10000;

        if ($hasConflict) {
            $score -= 2500;
        }

        $slack = $allocatedMinutes - $expectedTurnaround;
        if ($slack < 0) {
            $score -= min(4000, abs($slack) * 80);
        }

        if ($cutoffMargin !== null) {
            if ($cutoffMargin < 0) {
                $score -= 4500;
            } elseif ($cutoffMargin <= 30 && $direction !== 'inbound') {
                $score -= (30 - $cutoffMargin) * 50;
            }
        }

        return max(0, min(10000, $score));
    }

    private function classifyCutoffRisk(
        ?int $cutoffMargin,
        bool $hasConflict,
        int $allocatedMinutes,
        int $expectedTurnaround,
        string $direction,
    ): string {
        if ($cutoffMargin !== null && ($cutoffMargin < 0 || ($direction !== 'inbound' && $cutoffMargin <= 15))) {
            return 'CRITICAL';
        }

        if ($hasConflict || ($cutoffMargin !== null && $cutoffMargin <= 45)) {
            return 'HIGH';
        }

        if (($allocatedMinutes - $expectedTurnaround) < 0 || ($cutoffMargin !== null && $cutoffMargin <= 90)) {
            return 'MEDIUM';
        }

        return 'LOW';
    }
}
