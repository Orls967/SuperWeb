<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use Modules\Shared\Domain\Algorithms\Assignment\AssignmentCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver;
use Modules\Wms\Domain\Certificates\MissionAssignmentCertificate;

/**
 * J2 RobotTaskAllocator: menugaskan robot ke task gudang secara optimal
 * dengan mendelegasikan matriks biaya ke Shared::HungarianSolver.
 */
final readonly class RobotTaskAllocator
{
    public function __construct(
        private HungarianSolver $solver,
        private AssignmentCertificateVerifier $verifier,
    ) {}

    /**
     * @param  list<array{id:int, code:string, zone:string, battery_pct:int}>  $robots
     * @param  list<array{task_code:string, zone:string, distance_m:int}>  $tasks
     * @return array{
     *     assignments: list<array{robot_id:int, robot_code:string, task_code:string, cost:int}>,
     *     total_cost: int,
     *     certificate: MissionAssignmentCertificate
     * }
     */
    public function allocate(array $robots, array $tasks): array
    {
        if ($robots === [] || $tasks === []) {
            $emptyHash = MissionAssignmentCertificate::computeHash([], 0);

            return [
                'assignments' => [],
                'total_cost' => 0,
                'certificate' => new MissionAssignmentCertificate([], 0, true, $emptyHash),
            ];
        }

        $robotCount = count($robots);
        $taskCount = count($tasks);
        $colCount = max($robotCount, $taskCount);

        $costMatrix = [];
        for ($i = 0; $i < $robotCount; $i++) {
            $row = [];
            for ($j = 0; $j < $colCount; $j++) {
                if ($j >= $taskCount) {
                    $row[] = HungarianSolver::INF;

                    continue;
                }
                $robot = $robots[$i];
                $task = $tasks[$j];
                $zonePenalty = $robot['zone'] === $task['zone'] ? 0 : 25;
                $batteryPenalty = max(0, 100 - $robot['battery_pct']);
                $row[] = max(1, $task['distance_m'] + $zonePenalty + intdiv($batteryPenalty, 10));
            }
            $costMatrix[] = $row;
        }

        $result = $this->solver->solve($costMatrix, $this->verifier);

        $assignments = [];
        $certPairs = [];
        $totalCost = 0;
        foreach ($result->assignment as $rowIdx => $colIdx) {
            if ($colIdx >= $taskCount) {
                continue;
            }
            $cost = $costMatrix[$rowIdx][$colIdx];
            if ($cost >= HungarianSolver::INF) {
                continue;
            }
            $assignments[] = [
                'robot_id' => $robots[$rowIdx]['id'],
                'robot_code' => $robots[$rowIdx]['code'],
                'task_code' => $tasks[$colIdx]['task_code'],
                'cost' => $cost,
            ];
            $certPairs[] = [
                'robot_id' => $robots[$rowIdx]['id'],
                'task_code' => $tasks[$colIdx]['task_code'],
                'cost' => $cost,
            ];
            $totalCost += $cost;
        }

        $hash = MissionAssignmentCertificate::computeHash($certPairs, $totalCost);

        return [
            'assignments' => $assignments,
            'total_cost' => $totalCost,
            'certificate' => new MissionAssignmentCertificate(
                assignments: $certPairs,
                totalCost: $totalCost,
                solverVerified: true,
                hash: $hash,
            ),
        ];
    }
}
