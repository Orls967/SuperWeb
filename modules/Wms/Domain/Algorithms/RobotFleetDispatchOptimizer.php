<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver;
use Modules\Wms\Domain\Services\RoboticsZoneInterlockEvaluator;
use Modules\Wms\Domain\ValueObjects\IntervalWindow;

/**
 * Pengoptimal penugasan armada robotika murni: menggabungkan matriks biaya Hungarian,
 * penyaringan interlock zona staf, dan kelayakan anggaran energi baterai.
 */
final readonly class RobotFleetDispatchOptimizer
{
    public const int DUMMY_PAD_COST = 500_000_000;

    public const int STAFF_BLOCKED_PENALTY = 100_000_000;

    public function __construct(
        private HungarianSolver $hungarianSolver,
        private RobotEnergyConsumptionEstimator $energyEstimator,
        private RoboticsZoneInterlockEvaluator $interlockEvaluator,
    ) {}

    /**
     * @param  list<array{mission_id:int, zone_code:string, priority:int, distance_m:int, payload_kg:int, start_min:int, duration_min:int, staff_blocked:bool, capacity_k:int, active_reservations:list<array{mission_id:int, start_min:int, end_min:int, robot_id:int}>}>  $missions
     * @param  list<array{robot_id:int, battery_pct:int, position_x:int, position_y:int, is_online:bool}>  $robots
     * @return array{
     *     assignments:list<array{mission_id:int, robot_id:int, cost:int, energy_feasible:bool, interlock_allowed:bool}>,
     *     unassigned_mission_ids:list<int>,
     *     total_cost:int,
     *     dual_u:list<int>,
     *     dual_v:list<int>
     * }
     */
    public function optimize(array $missions, array $robots): array
    {
        $onlineRobots = array_values(array_filter(
            $robots,
            static fn (array $r): bool => (bool) $r['is_online'] && (int) $r['battery_pct'] >= RobotEnergyConsumptionEstimator::DEFAULT_RESERVE_MIN_PCT,
        ));

        if ($missions === [] || $onlineRobots === []) {
            return [
                'assignments' => [],
                'unassigned_mission_ids' => array_map(static fn (array $m): int => (int) $m['mission_id'], $missions),
                'total_cost' => 0,
                'dual_u' => [],
                'dual_v' => [],
            ];
        }

        $nMissions = count($missions);
        $nRobots = count($onlineRobots);
        $dim = max($nMissions, $nRobots);

        $costMatrix = [];
        $feasibilityGrid = [];

        for ($i = 0; $i < $dim; $i++) {
            $row = [];
            for ($j = 0; $j < $dim; $j++) {
                if ($i >= $nMissions || $j >= $nRobots) {
                    $row[] = self::DUMMY_PAD_COST;
                    continue;
                }

                $m = $missions[$i];
                $r = $onlineRobots[$j];

                $window = new IntervalWindow(
                    max(0, (int) $m['start_min']),
                    max(1, (int) $m['start_min'] + max(1, (int) $m['duration_min'])),
                );
                $interlock = $this->interlockEvaluator->evaluateEntry(
                    zoneCode: (string) $m['zone_code'],
                    capacityK: max(1, (int) $m['capacity_k']),
                    staffBlocked: (bool) $m['staff_blocked'],
                    blockStartMin: null,
                    blockEndMin: null,
                    requestedWindow: $window,
                    activeReservations: $m['active_reservations'],
                    robotBatteryPct: (int) $r['battery_pct'],
                );

                $energyCert = $this->energyEstimator->estimateBatch(
                    robotId: (int) $r['robot_id'],
                    initialBatteryPct: (int) $r['battery_pct'],
                    missions: [[
                        'mission_id' => (int) $m['mission_id'],
                        'distance_m' => max(1, (int) $m['distance_m']),
                        'payload_kg' => max(0, (int) $m['payload_kg']),
                    ]],
                );

                $zoneDist = abs(crc32((string) $m['zone_code']) % 50) + (int) $r['position_x'] + (int) $r['position_y'];
                $batteryPenalty = (100 - (int) $r['battery_pct']) * 15;
                $priorityPenalty = max(0, 100 - (int) $m['priority']) * 50;
                $interlockPenalty = $interlock['allowed'] ? 0 : self::STAFF_BLOCKED_PENALTY;
                $energyPenalty = $energyCert->allFeasible ? 0 : 25_000_000;

                $row[] = ($zoneDist * 10) + $batteryPenalty + $priorityPenalty + $interlockPenalty + $energyPenalty;
                $feasibilityGrid[$i][$j] = [
                    'energy_feasible' => $energyCert->allFeasible,
                    'interlock_allowed' => (bool) $interlock['allowed'],
                ];
            }
            $costMatrix[] = $row;
        }

        $solution = $this->hungarianSolver->solve($costMatrix);
        $assignments = [];
        $unassigned = [];
        $totalCost = 0;

        for ($i = 0; $i < $nMissions; $i++) {
            $m = $missions[$i];
            $robotIdx = $solution->assignment[$i] ?? -1;

            if ($robotIdx < 0 || $robotIdx >= $nRobots) {
                $unassigned[] = (int) $m['mission_id'];
                continue;
            }

            $meta = $feasibilityGrid[$i][$robotIdx];
            if (! $meta['interlock_allowed'] || ! $meta['energy_feasible']) {
                $unassigned[] = (int) $m['mission_id'];
                continue;
            }

            $cost = (int) $costMatrix[$i][$robotIdx];
            $totalCost += $cost;
            $assignments[] = [
                'mission_id' => (int) $m['mission_id'],
                'robot_id' => (int) $onlineRobots[$robotIdx]['robot_id'],
                'cost' => $cost,
                'energy_feasible' => true,
                'interlock_allowed' => true,
            ];
        }

        return [
            'assignments' => $assignments,
            'unassigned_mission_ids' => $unassigned,
            'total_cost' => $totalCost,
            'dual_u' => $solution->u,
            'dual_v' => $solution->v,
        ];
    }
}
