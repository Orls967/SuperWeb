<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use App\Models\User;
use Modules\Core\Domain\Models\CoreTask;

/**
 * Service pemrosesan tugas massal (Batch Action Engine) untuk persetujuan atau delegasi bersamaan.
 */
class TaskBatchOperationService
{
    public function __construct(
        protected TaskInboxService $inboxService,
    ) {}

    /**
     * Menyetujui sekumpulan tugas secara massal dengan verifikasi otorisasi per item.
     *
     * @param  string[]  $taskIds
     * @return array{success_count: int, failed_count: int, failed_ids: string[]}
     */
    public function batchApprove(array $taskIds, User $actor, ?string $notes = null): array
    {
        $success = 0;
        $failed = 0;
        $failedIds = [];

        $tasks = CoreTask::query()->whereIn('id', $taskIds)->get();

        foreach ($tasks as $task) {
            // Verifikasi hak akses
            if ($actor->role !== 'admin' && $task->assignee_user_id !== $actor->id && $task->assignee_role !== $actor->role) {
                $failed++;
                $failedIds[] = $task->id;

                continue;
            }

            if (! in_array($task->status, ['pending', 'in_progress', 'delegated', 'escalated'], true)) {
                $failed++;
                $failedIds[] = $task->id;

                continue;
            }

            try {
                $this->inboxService->approveTask($task, $actor->id, $notes ?? 'Persetujuan massal terverifikasi');
                $success++;
            } catch (\Throwable) {
                $failed++;
                $failedIds[] = $task->id;
            }
        }

        return [
            'success_count' => $success,
            'failed_count' => $failed,
            'failed_ids' => $failedIds,
        ];
    }

    /**
     * Mendelegasikan sekumpulan tugas secara massal kepada satu target pengguna.
     *
     * @param  string[]  $taskIds
     * @return array{success_count: int, failed_count: int}
     */
    public function batchDelegate(array $taskIds, string $newAssigneeId, User $actor, ?string $notes = null): array
    {
        $success = 0;
        $failed = 0;

        $tasks = CoreTask::query()->whereIn('id', $taskIds)->get();

        foreach ($tasks as $task) {
            if ($actor->role !== 'admin' && $task->assignee_user_id !== $actor->id) {
                $failed++;

                continue;
            }

            try {
                $this->inboxService->delegateTask($task, $newAssigneeId, $notes ?? 'Delegasi massal');
                $success++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return [
            'success_count' => $success,
            'failed_count' => $failed,
        ];
    }
}
