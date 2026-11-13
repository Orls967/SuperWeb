<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Application\Services\RobotMissionTransitionService;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Infrastructure\Adapters\WarehouseTaskCompletionBridge;
use Modules\Wms\Models\RobotMission;

/**
 * Action coba ulang misi gagal (maks 2 kali) atau pengalihan ke fallback_manual + re-plan wave (D279-1, D279-5).
 */
final readonly class RetryOrFallbackRobotMissionAction
{
    public function __construct(
        private RobotMissionTransitionService $transitionService,
        private WarehouseTaskCompletionBridge $taskBridge,
        private PlanRoboticsWaveAction $waveAction,
    ) {}

    public function retry(RobotMission $mission, ?int $actorUserId = null): RobotMission
    {
        return $this->transitionService->transition(
            $mission,
            RobotMissionStateMachine::STATUS_QUEUED,
            $actorUserId,
            'RETRY_FAILED_MISSION',
            'Misi dijadwalkan ulang ke antrean.',
        );
    }

    /**
     * @param  list<array{id: string|int, size: int, carrier: string, cutoff: int}>  $remainingOrders
     * @return array{mission: RobotMission, replanned_waves_count: int}
     */
    public function fallbackAndReplan(
        RobotMission $mission,
        int $operatorUserId,
        string $reason,
        int $waveCapacity,
        array $remainingOrders,
    ): array {
        return DB::transaction(function () use ($mission, $operatorUserId, $reason, $waveCapacity, $remainingOrders): array {
            /** @var RobotMission $locked */
            $locked = RobotMission::query()->lockForUpdate()->findOrFail($mission->id);

            if ($locked->status !== RobotMissionStateMachine::STATUS_FAILED) {
                $locked = $this->transitionService->transition(
                    $locked,
                    RobotMissionStateMachine::STATUS_FAILED,
                    $operatorUserId,
                    $reason,
                    'Misi gagal sebelum fallback manual.',
                );
            }

            $this->taskBridge->createManualFallbackTask($locked, $operatorUserId, $reason);
            $replan = $this->waveAction->replanAfterFallback(
                (string) $locked->warehouse_id,
                $locked,
                $waveCapacity,
                $remainingOrders,
            );

            $updated = $this->transitionService->transition(
                $locked,
                RobotMissionStateMachine::STATUS_FALLBACK_MANUAL,
                $operatorUserId,
                $reason,
                'Task manual dibuat dan wave disusun ulang.',
                [
                    'manual_task_created' => true,
                    'wave_replanned' => true,
                ],
            );

            return [
                'mission' => $updated,
                'replanned_waves_count' => count($replan['waves']),
            ];
        });
    }
}
