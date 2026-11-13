<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Application\Services\RobotMissionTransitionService;
use Modules\Wms\Contracts\SimulatedDeviceAdapterInterface;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Infrastructure\Adapters\WarehouseTaskCompletionBridge;
use Modules\Wms\Models\RobotMission;

/**
 * Action penyelesaian bertahap misi robot (moving -> picking -> delivering -> completed)
 * dengan verifikasi perangkat simulasi dan konfirmasi WarehouseTask Fase 41 (D279-1, D279-8).
 */
final readonly class CompleteRobotMissionAction
{
    public function __construct(
        private RobotMissionTransitionService $transitionService,
        private SimulatedDeviceAdapterInterface $deviceAdapter,
        private WarehouseTaskCompletionBridge $taskBridge,
    ) {}

    public function execute(RobotMission $mission, ?int $actorUserId = null): RobotMission
    {
        return DB::transaction(function () use ($mission, $actorUserId): RobotMission {
            /** @var RobotMission $current */
            $current = RobotMission::query()->lockForUpdate()->findOrFail($mission->id);

            $robotCode = $current->robot?->robot_code ?? "UNIT-{$current->robot_unit_id}";

            if ($current->status === RobotMissionStateMachine::STATUS_MOVING) {
                $this->deviceAdapter->sendCommand($robotCode, 'ARRIVE_SOURCE_BIN', ['mission' => $current->mission_code]);
                $current = $this->transitionService->transition(
                    $current,
                    RobotMissionStateMachine::STATUS_PICKING,
                    $actorUserId,
                    'ARRIVED_SOURCE_BIN',
                    'Robot tiba di bin sumber.',
                    ['arrived_at_source_bin' => true],
                );
            }

            if ($current->status === RobotMissionStateMachine::STATUS_PICKING) {
                $this->deviceAdapter->sendCommand($robotCode, 'SCAN_AND_PICK', ['mission' => $current->mission_code]);
                $current = $this->transitionService->transition(
                    $current,
                    RobotMissionStateMachine::STATUS_DELIVERING,
                    $actorUserId,
                    'ITEM_SCANNED',
                    'Item terpindai, robot menuju tujuan.',
                    ['item_scanned' => true],
                );
            }

            if ($current->status === RobotMissionStateMachine::STATUS_DELIVERING) {
                $this->deviceAdapter->sendCommand($robotCode, 'DELIVER_STATION', ['mission' => $current->mission_code]);
                $this->taskBridge->confirmLinkedTask($current);
                $current = $this->transitionService->transition(
                    $current,
                    RobotMissionStateMachine::STATUS_COMPLETED,
                    $actorUserId,
                    'MISSION_DELIVERED',
                    'Misi selesai dan WarehouseTask terkonfirmasi.',
                    [
                        'arrived_at_destination' => true,
                        'warehouse_task_confirmed' => true,
                    ],
                );
            }

            return $current;
        });
    }
}
