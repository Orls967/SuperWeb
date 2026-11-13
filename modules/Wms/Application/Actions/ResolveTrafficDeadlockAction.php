<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Application\Services\RobotMissionTransitionService;
use Modules\Wms\Domain\Algorithms\TrafficDeadlockDetector;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\RobotMission;

/**
 * Action deteksi dan resolusi kebuntuan lalu lintas misi robot (X279-3).
 *
 * Memilih misi berprioritas terendah dalam siklus dan memindahkannya ke status failed
 * sehingga reservasi zonanya otomatis dilepaskan sebagai kompensasi.
 */
final readonly class ResolveTrafficDeadlockAction
{
    public function __construct(
        private TrafficDeadlockDetector $detector,
        private RobotMissionTransitionService $transitionService,
    ) {}

    /**
     * @param  array<string, string>  $waitForEdges  Peta mission_code => target_mission_code
     * @return array{has_deadlock: bool, cycle_nodes: list<string>, victim_node: string|null, resolved: bool}
     */
    public function execute(string $warehouseId, array $waitForEdges, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($warehouseId, $waitForEdges, $actorUserId): array {
            $missions = RobotMission::query()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('mission_code', array_unique(array_merge(array_keys($waitForEdges), array_values($waitForEdges))))
                ->get()
                ->keyBy('mission_code');

            $priorities = [];
            foreach ($missions as $code => $mission) {
                $priorities[(string) $code] = (int) $mission->priority;
            }

            $detection = $this->detector->detectAndResolve($waitForEdges, $priorities);
            $resolved = false;

            if ($detection['has_deadlock'] && $detection['victim_node'] !== null) {
                /** @var RobotMission|null $victim */
                $victim = $missions->get($detection['victim_node']);
                if ($victim !== null && RobotMissionStateMachine::canTransition((string) $victim->status, RobotMissionStateMachine::STATUS_FAILED)) {
                    $this->transitionService->transition(
                        $victim,
                        RobotMissionStateMachine::STATUS_FAILED,
                        $actorUserId,
                        'DEADLOCK_VICTIM_RESOLUTION',
                        'Misi berprioritas terendah dibatalkan untuk memecah siklus deadlock: '.implode(' -> ', $detection['cycle_nodes']),
                    );
                    $resolved = true;
                }
            }

            return [
                'has_deadlock' => $detection['has_deadlock'],
                'cycle_nodes' => $detection['cycle_nodes'],
                'victim_node' => $detection['victim_node'],
                'resolved' => $resolved,
            ];
        });
    }
}
