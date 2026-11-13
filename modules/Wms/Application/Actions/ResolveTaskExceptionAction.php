<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use InvalidArgumentException;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\StateMachines\WarehouseTaskStateMachine;

/**
 * Menangani resolusi atau retry tugas gudang yang berstatus exception.
 */
final class ResolveTaskExceptionAction
{
    public function __construct(
        private readonly WarehouseTaskStateMachine $stateMachine,
    ) {}

    public function flag(string|int $taskId, int $actorUserId, string $reason): Task
    {
        $task = Task::findOrFail($taskId);

        return $this->stateMachine->transition($task, 'flag_exception', $actorUserId, $reason);
    }

    public function resolve(string|int $taskId, int $actorUserId, string $resolution, string $mode = 'resolve'): Task
    {
        if (! in_array($mode, ['resolve', 'retry', 'cancel'], true)) {
            throw new InvalidArgumentException("Mode resolusi tidak valid: {$mode}");
        }

        $task = Task::findOrFail($taskId);

        return $this->stateMachine->transition($task, $mode, $actorUserId, $resolution);
    }
}
