<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use InvalidArgumentException;
use Modules\Wms\Domain\Algorithms\PickPathPlanner;
use Modules\Wms\Domain\Models\Task;

/**
 * Layanan optimasi lintasan wave picking berbasis zona, jarak Manhattan,
 * deteksi kemacetan pick-face, dan estimasi durasi eksekusi (integer detik).
 */
final class WavePickPathOptimizationService
{
    public const DEFAULT_AISLE_WIDTH = 4;

    public const DEFAULT_AISLE_LENGTH = 20;

    public const DEFAULT_CONGESTION_THRESHOLD = 3;

    public const WALK_SECONDS_PER_METER = 1;

    public const SCAN_SECONDS_PER_STOP = 12;

    public const HANDLING_SECONDS_PER_UNIT = 3;

    public const CONGESTION_PENALTY_SECONDS = 25;

    public function __construct(
        private readonly PickPathPlanner $pickPathPlanner = new PickPathPlanner,
    ) {}

    /**
     * Mengelompokkan tugas pick per zona/lorong, menghitung urutan rute terpendek,
     * mendeteksi kemacetan pick-face, dan mengestimasi total waktu pick (detik).
     *
     * @param  list<array{
     *     task_id?: int|string,
     *     wave_id?: int|string|null,
     *     product_id?: int,
     *     qty: int,
     *     zone_code?: string,
     *     aisle: int,
     *     pos: int,
     *     bin_id?: string,
     *     is_pick_face?: bool
     * }>  $tasks
     * @return array{
     *     total_zones: int,
     *     total_picks: int,
     *     total_units: int,
     *     total_manhattan_distance: int,
     *     total_planner_distance: int,
     *     estimated_total_seconds: int,
     *     congestion_count: int,
     *     congested_pick_faces: list<array{
     *         zone_code: string,
     *         bin_id: string,
     *         aisle: int,
     *         pos: int,
     *         task_count: int,
     *         total_qty: int,
     *         severity: string,
     *         congestion_penalty_seconds: int
     *     }>,
     *     zone_plans: list<array{
     *         zone_code: string,
     *         strategy: string,
     *         aisle_count: int,
     *         stop_count: int,
     *         task_count: int,
     *         total_units: int,
     *         manhattan_distance: int,
     *         planner_distance: int,
     *         s_shape_distance: int,
     *         largest_gap_distance: int,
     *         estimated_seconds: int,
     *         congestion_penalty_seconds: int,
     *         ordered_sequence: list<array{aisle: int, pos: int, bin_id: string, task_count: int, total_qty: int}>,
     *         certificate_hash: string
     *     }>
     * }
     */
    public function optimize(
        array $tasks,
        int $aisleWidth = self::DEFAULT_AISLE_WIDTH,
        int $aisleLength = self::DEFAULT_AISLE_LENGTH,
        int $congestionThreshold = self::DEFAULT_CONGESTION_THRESHOLD,
    ): array {
        if ($aisleWidth <= 0 || $aisleLength <= 0) {
            throw new InvalidArgumentException('Dimensi lorong harus bernilai positif.');
        }
        if ($congestionThreshold < 2) {
            throw new InvalidArgumentException('Ambang batas kemacetan pick-face minimal 2 tugas.');
        }

        if ($tasks === []) {
            return [
                'total_zones' => 0,
                'total_picks' => 0,
                'total_units' => 0,
                'total_manhattan_distance' => 0,
                'total_planner_distance' => 0,
                'estimated_total_seconds' => 0,
                'congestion_count' => 0,
                'congested_pick_faces' => [],
                'zone_plans' => [],
            ];
        }

        $byZone = $this->groupTasksByZone($tasks);

        $zonePlans = [];
        $congestedPickFaces = [];
        $totalPicks = 0;
        $totalUnits = 0;
        $totalManhattanDistance = 0;
        $totalPlannerDistance = 0;
        $estimatedTotalSeconds = 0;

        foreach ($byZone as $zoneCode => $zoneTasks) {
            $zonePlan = $this->optimizeZone(
                zoneCode: $zoneCode,
                zoneTasks: $zoneTasks,
                aisleWidth: $aisleWidth,
                aisleLength: $aisleLength,
                congestionThreshold: $congestionThreshold,
            );

            $zonePlans[] = $zonePlan['plan'];
            array_push($congestedPickFaces, ...$zonePlan['congestions']);

            $totalPicks += $zonePlan['plan']['task_count'];
            $totalUnits += $zonePlan['plan']['total_units'];
            $totalManhattanDistance += $zonePlan['plan']['manhattan_distance'];
            $totalPlannerDistance += $zonePlan['plan']['planner_distance'];
            $estimatedTotalSeconds += $zonePlan['plan']['estimated_seconds'];
        }

        return [
            'total_zones' => count($zonePlans),
            'total_picks' => $totalPicks,
            'total_units' => $totalUnits,
            'total_manhattan_distance' => $totalManhattanDistance,
            'total_planner_distance' => $totalPlannerDistance,
            'estimated_total_seconds' => $estimatedTotalSeconds,
            'congestion_count' => count($congestedPickFaces),
            'congested_pick_faces' => $congestedPickFaces,
            'zone_plans' => $zonePlans,
        ];
    }

    /**
     * Mengadaptasi koleksi model Eloquent Task (dengan relasi bin.rack.zone) ke optimasi rute.
     *
     * @param  iterable<Task>  $tasks
     * @return array<string, mixed>
     */
    public function optimizeFromEloquentTasks(
        iterable $tasks,
        int $aisleWidth = self::DEFAULT_AISLE_WIDTH,
        int $aisleLength = self::DEFAULT_AISLE_LENGTH,
        int $congestionThreshold = self::DEFAULT_CONGESTION_THRESHOLD,
    ): array {
        $normalized = [];

        foreach ($tasks as $task) {
            $bin = $task->bin;
            $rack = $bin?->rack;
            $zone = $rack?->zone;

            $rackCode = (string) ($rack->code ?? 'R1');
            $binCode = (string) ($bin->code ?? 'B-01');
            $coord = $this->parseCoordinateFromCode($rackCode, $binCode);

            $normalized[] = [
                'task_id' => $task->id,
                'wave_id' => $task->wave_id,
                'product_id' => (int) $task->product_id,
                'qty' => max(1, (int) round((float) $task->qty)),
                'zone_code' => (string) ($zone->code ?? 'GENERAL'),
                'aisle' => $coord['aisle'],
                'pos' => $coord['pos'],
                'bin_id' => $bin !== null ? "{$rackCode}-{$binCode}" : "BIN-{$task->id}",
                'is_pick_face' => (bool) ($bin->is_pick_face ?? true),
            ];
        }

        return $this->optimize($normalized, $aisleWidth, $aisleLength, $congestionThreshold);
    }

    /**
     * Mengekstrak koordinat integer (lorong, posisi) dari kode rak dan bin gudang.
     *
     * @return array{aisle: int, pos: int}
     */
    public function parseCoordinateFromCode(string $rackCode, string $binCode): array
    {
        preg_match('/(\d+)/', $rackCode, $rackMatch);
        preg_match('/(\d+)/', $binCode, $binMatch);

        $aisle = isset($rackMatch[1]) ? max(1, (int) $rackMatch[1]) : 1;
        $pos = isset($binMatch[1]) ? max(1, (int) $binMatch[1]) : 1;

        return [
            'aisle' => $aisle,
            'pos' => $pos,
        ];
    }

    public function manhattanDistance(int $aisleA, int $posA, int $aisleB, int $posB, int $aisleWidth): int
    {
        return (abs($aisleA - $aisleB) * $aisleWidth) + abs($posA - $posB);
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return array<string, list<array{aisle: int, pos: int, bin_id: string, qty: int, is_pick_face: bool}>>
     */
    private function groupTasksByZone(array $tasks): array
    {
        $grouped = [];

        foreach ($tasks as $task) {
            $aisle = max(1, (int) $task['aisle']);
            $pos = max(0, (int) $task['pos']);
            $zoneCode = strtoupper(trim((string) ($task['zone_code'] ?? 'GENERAL')));
            if ($zoneCode === '') {
                $zoneCode = 'GENERAL';
            }
            $binId = (string) ($task['bin_id'] ?? "A{$aisle}-P{$pos}");

            $grouped[$zoneCode][] = [
                'aisle' => $aisle,
                'pos' => $pos,
                'bin_id' => $binId,
                'qty' => max(1, (int) $task['qty']),
                'is_pick_face' => (bool) ($task['is_pick_face'] ?? true),
            ];
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @param  list<array{aisle: int, pos: int, bin_id: string, qty: int, is_pick_face: bool}>  $zoneTasks
     * @return array{
     *     plan: array{
     *         zone_code: string,
     *         strategy: string,
     *         aisle_count: int,
     *         stop_count: int,
     *         task_count: int,
     *         total_units: int,
     *         manhattan_distance: int,
     *         planner_distance: int,
     *         s_shape_distance: int,
     *         largest_gap_distance: int,
     *         estimated_seconds: int,
     *         congestion_penalty_seconds: int,
     *         ordered_sequence: list<array{aisle: int, pos: int, bin_id: string, task_count: int, total_qty: int}>,
     *         certificate_hash: string
     *     },
     *     congestions: list<array{
     *         zone_code: string,
     *         bin_id: string,
     *         aisle: int,
     *         pos: int,
     *         task_count: int,
     *         total_qty: int,
     *         severity: string,
     *         congestion_penalty_seconds: int
     *     }>
     * }
     */
    private function optimizeZone(
        string $zoneCode,
        array $zoneTasks,
        int $aisleWidth,
        int $aisleLength,
        int $congestionThreshold,
    ): array {
        $stopBuckets = [];
        $aisleSet = [];
        $maxPos = 0;
        $totalUnits = 0;

        foreach ($zoneTasks as $item) {
            $aisleSet[$item['aisle']] = true;
            $maxPos = max($maxPos, $item['pos']);
            $totalUnits += $item['qty'];

            $stopKey = "{$item['aisle']}:{$item['pos']}:{$item['bin_id']}";
            if (! isset($stopBuckets[$stopKey])) {
                $stopBuckets[$stopKey] = [
                    'aisle' => $item['aisle'],
                    'pos' => $item['pos'],
                    'bin_id' => $item['bin_id'],
                    'task_count' => 0,
                    'total_qty' => 0,
                    'is_pick_face' => $item['is_pick_face'],
                ];
            }

            $stopBuckets[$stopKey]['task_count']++;
            $stopBuckets[$stopKey]['total_qty'] += $item['qty'];
        }

        $congestions = [];
        $congestionPenaltySeconds = 0;
        foreach ($stopBuckets as $bucket) {
            if ($bucket['is_pick_face'] && $bucket['task_count'] >= $congestionThreshold) {
                $excessTasks = $bucket['task_count'] - $congestionThreshold + 1;
                $penalty = $excessTasks * self::CONGESTION_PENALTY_SECONDS;
                $congestionPenaltySeconds += $penalty;
                $congestions[] = [
                    'zone_code' => $zoneCode,
                    'bin_id' => $bucket['bin_id'],
                    'aisle' => $bucket['aisle'],
                    'pos' => $bucket['pos'],
                    'task_count' => $bucket['task_count'],
                    'total_qty' => $bucket['total_qty'],
                    'severity' => $bucket['task_count'] >= ($congestionThreshold * 2) ? 'HIGH' : 'MODERATE',
                    'congestion_penalty_seconds' => $penalty,
                ];
            }
        }

        $uniqueStops = array_map(
            static fn (array $bucket): array => [
                'aisle' => $bucket['aisle'],
                'pos' => $bucket['pos'],
                'bin_id' => $bucket['bin_id'],
            ],
            array_values($stopBuckets)
        );

        $effectiveAisleLength = max($aisleLength, $maxPos + 1);
        $planned = $this->pickPathPlanner->plan($aisleWidth, $effectiveAisleLength, $uniqueStops);

        $orderedSequence = [];
        $manhattanDistance = 0;
        $prevAisle = 1;
        $prevPos = 0;

        foreach ($planned['sequence'] as $stop) {
            $manhattanDistance += $this->manhattanDistance(
                $prevAisle,
                $prevPos,
                $stop['aisle'],
                $stop['pos'],
                $aisleWidth
            );
            $prevAisle = $stop['aisle'];
            $prevPos = $stop['pos'];

            $stopKey = "{$stop['aisle']}:{$stop['pos']}:{$stop['bin_id']}";
            $bucket = $stopBuckets[$stopKey];
            $orderedSequence[] = [
                'aisle' => $stop['aisle'],
                'pos' => $stop['pos'],
                'bin_id' => $stop['bin_id'],
                'task_count' => $bucket['task_count'],
                'total_qty' => $bucket['total_qty'],
            ];
        }

        // Jarak kembali ke staging I/O (lorong 1, pos 0)
        $manhattanDistance += $this->manhattanDistance($prevAisle, $prevPos, 1, 0, $aisleWidth);

        $walkSeconds = $manhattanDistance * self::WALK_SECONDS_PER_METER;
        $scanSeconds = count($orderedSequence) * self::SCAN_SECONDS_PER_STOP;
        $handlingSeconds = $totalUnits * self::HANDLING_SECONDS_PER_UNIT;
        $estimatedSeconds = $walkSeconds + $scanSeconds + $handlingSeconds + $congestionPenaltySeconds;

        return [
            'plan' => [
                'zone_code' => $zoneCode,
                'strategy' => $planned['strategy'],
                'aisle_count' => count($aisleSet),
                'stop_count' => count($orderedSequence),
                'task_count' => count($zoneTasks),
                'total_units' => $totalUnits,
                'manhattan_distance' => $manhattanDistance,
                'planner_distance' => $planned['total_distance'],
                's_shape_distance' => $planned['s_shape_distance'],
                'largest_gap_distance' => $planned['largest_gap_distance'],
                'estimated_seconds' => $estimatedSeconds,
                'congestion_penalty_seconds' => $congestionPenaltySeconds,
                'ordered_sequence' => $orderedSequence,
                'certificate_hash' => $planned['certificate']->signatureHash,
            ],
            'congestions' => $congestions,
        ];
    }
}
