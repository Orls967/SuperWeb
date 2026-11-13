<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\TaskEvent;
use Modules\Wms\Domain\StateMachines\WarehouseTaskStateMachine;

/**
 * Layanan siklus hidup tugas gudang berbasis 7-state machine & riwayat event append-only.
 */
final class WmsTaskLifecycleService
{
    public function __construct(
        private readonly WarehouseTaskStateMachine $stateMachine,
    ) {}

    public function createTask(
        string|int $warehouseId,
        string $kind,
        int $productId,
        int $qtyBase,
        string|int|null $fromBinId = null,
        string|int|null $toBinId = null,
    ): Task {
        return Task::create([
            'warehouse_id' => $warehouseId,
            'kind' => $kind,
            'status' => WarehouseTaskStateMachine::STATUS_CREATED,
            'product_id' => $productId,
            'from_bin_id' => $fromBinId,
            'to_bin_id' => $toBinId,
            'qty' => $qtyBase,
        ]);
    }

    public function assign(string|int $taskId, int $operatorUserId): Task
    {
        $task = Task::findOrFail($taskId);

        return $this->stateMachine->transition($task, 'assign', $operatorUserId);
    }

    public function start(string|int $taskId, int $operatorUserId): Task
    {
        $task = Task::findOrFail($taskId);

        return $this->stateMachine->transition($task, 'start', $operatorUserId);
    }

    public function confirm(string|int $taskId, int $operatorUserId, ?string $note = null): Task
    {
        $task = Task::findOrFail($taskId);

        return $this->stateMachine->transition($task, 'confirm', $operatorUserId, $note);
    }

    public function close(string|int $taskId, int $actorUserId): Task
    {
        $task = Task::findOrFail($taskId);

        return $this->stateMachine->transition($task, 'close', $actorUserId);
    }

    public function cancel(string|int $taskId, int $actorUserId, string $reason): Task
    {
        $task = Task::findOrFail($taskId);

        return $this->stateMachine->transition($task, 'cancel', $actorUserId, $reason);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getEventHistory(string|int $taskId): array
    {
        return TaskEvent::where('task_id', $taskId)
            ->orderBy('id')
            ->get()
            ->map(static fn (TaskEvent $e): array => [
                'from_status' => $e->from_status,
                'to_status' => $e->to_status,
                'action' => $e->action,
                'actor_user_id' => $e->actor_user_id,
                'reason' => $e->reason,
                'created_at' => $e->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(string|int $warehouseId): array
    {
        $rows = DB::table('wms_tasks')
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $out = [];
        foreach (WarehouseTaskStateMachine::allStates() as $state) {
            $out[$state] = (int) ($rows[$state] ?? 0);
        }

        return $out;
    }
}
