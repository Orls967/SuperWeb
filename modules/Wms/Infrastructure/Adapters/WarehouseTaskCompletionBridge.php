<?php

declare(strict_types=1);

namespace Modules\Wms\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Domain\Models\Task as WarehouseTask;

/**
 * Jembatan internal modul Wms antara RobotMission (Fase 279) dan WarehouseTask (Fase 41).
 */
final readonly class WarehouseTaskCompletionBridge
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function confirmLinkedTask(RobotMission $mission): ?WarehouseTask
    {
        if ($mission->warehouse_task_id === null) {
            return null;
        }

        /** @var WarehouseTask|null $task */
        $task = WarehouseTask::query()->lockForUpdate()->find($mission->warehouse_task_id);
        if ($task === null) {
            return null;
        }

        if (! in_array((string) $task->status, ['confirmed', 'closed'], true)) {
            $task->update([
                'status' => 'confirmed',
                'completed_at' => $this->clock->now(),
            ]);
        }

        return $task->fresh();
    }

    public function createManualFallbackTask(RobotMission $mission, int $operatorUserId, string $reason): WarehouseTask
    {
        return DB::transaction(function () use ($mission, $operatorUserId, $reason): WarehouseTask {
            if ($mission->warehouse_task_id !== null) {
                /** @var WarehouseTask|null $existing */
                $existing = WarehouseTask::query()->lockForUpdate()->find($mission->warehouse_task_id);
                if ($existing !== null) {
                    $existing->update([
                        'status' => 'open',
                        'priority' => 'high',
                        'assigned_to_user_id' => $operatorUserId,
                        'source_ref' => 'MAN-'.$mission->mission_code,
                    ]);
                    $mission->assigned_operator_user_id = $operatorUserId;
                    $mission->save();

                    return $existing->fresh();
                }
            }

            $task = WarehouseTask::query()->create([
                'kind' => 'pick',
                'status' => 'open',
                'priority' => 'high',
                'product_id' => 1,
                'qty' => '1.000000',
                'source_ref' => 'MAN-'.$mission->mission_code,
                'assigned_to_user_id' => $operatorUserId,
                'created_by_user_id' => $operatorUserId,
            ]);

            $mission->warehouse_task_id = $task->id;
            $mission->assigned_operator_user_id = $operatorUserId;
            $mission->save();

            return $task;
        });
    }
}
