<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use Modules\Core\Domain\Models\CoreTask;
use Modules\Core\Domain\Models\CoreTaskHistory;

/**
 * Service pencatat audit siklus hidup tugas (Task Lifecycle Audit Recorder).
 */
class TaskLifecycleAuditRecorder
{
    /**
     * Mencatat peristiwa perubahan status atau mutasi penting pada tugas.
     */
    public function recordEvent(
        CoreTask $task,
        string $action,
        ?string $fromStatus,
        string $toStatus,
        ?string $actorId = null,
        ?string $notes = null
    ): CoreTaskHistory {
        return CoreTaskHistory::create([
            'task_id' => $task->id,
            'actor_id' => $actorId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
        ]);
    }
}
