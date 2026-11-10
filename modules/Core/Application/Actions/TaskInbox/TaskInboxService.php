<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreTask;
use Modules\Core\Domain\Models\CoreTaskHistory;

/**
 * Service manajemen Kotak Masuk "Tugas Saya" (§19.1).
 * Mengelola siklus hidup tugas, persetujuan, penolakan, delegasi, eskalasi,
 * dan audit trail riwayat tindakan kerja terpusat.
 */
class TaskInboxService
{
    /**
     * Membuat tugas baru di Kotak Masuk.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTask(array $data): CoreTask
    {
        $task = CoreTask::create([
            'id' => $data['id'] ?? (string) Str::uuid(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'module' => $data['module'] ?? 'Core',
            'source_entity_type' => $data['source_entity_type'],
            'source_entity_id' => (string) $data['source_entity_id'],
            'assignee_user_id' => $data['assignee_user_id'] ?? null,
            'assignee_role' => $data['assignee_role'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'priority' => $data['priority'] ?? 'normal',
            'due_at' => $data['due_at'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'payload' => $data['payload'] ?? null,
            'escalation_level' => $data['escalation_level'] ?? 0,
        ]);

        CoreTaskHistory::create([
            'task_id' => $task->id,
            'actor_id' => $data['creator_id'] ?? null,
            'action' => 'created',
            'from_status' => null,
            'to_status' => $task->status,
            'notes' => 'Task initialized in Inbox',
        ]);

        return $task;
    }

    /**
     * Menyetujui tugas.
     */
    public function approveTask(CoreTask $task, string|int|null $actorId, ?string $notes = null): CoreTask
    {
        $fromStatus = $task->status;
        $task->status = 'approved';
        $task->save();

        CoreTaskHistory::create([
            'task_id' => $task->id,
            'actor_id' => $actorId !== null ? (string) $actorId : null,
            'action' => 'approved',
            'from_status' => $fromStatus,
            'to_status' => 'approved',
            'notes' => $notes,
        ]);

        return $task;
    }

    /**
     * Menolak tugas.
     */
    public function rejectTask(CoreTask $task, string|int|null $actorId, ?string $notes = null): CoreTask
    {
        $fromStatus = $task->status;
        $task->status = 'rejected';
        $task->save();

        CoreTaskHistory::create([
            'task_id' => $task->id,
            'actor_id' => $actorId !== null ? (string) $actorId : null,
            'action' => 'rejected',
            'from_status' => $fromStatus,
            'to_status' => 'rejected',
            'notes' => $notes,
        ]);

        return $task;
    }

    /**
     * Mendelegasikan tugas ke pengguna lain.
     */
    public function delegateTask(CoreTask $task, string|int $newAssigneeId, ?string $notes = null): CoreTask
    {
        $fromStatus = $task->status;
        $task->status = 'delegated';
        $task->assignee_user_id = (string) $newAssigneeId;
        $task->save();

        CoreTaskHistory::create([
            'task_id' => $task->id,
            'actor_id' => (string) $newAssigneeId,
            'action' => 'delegated',
            'from_status' => $fromStatus,
            'to_status' => 'delegated',
            'notes' => $notes,
        ]);

        return $task;
    }

    /**
     * Menaikkan tingkat eskalasi tugas yang tertunda.
     */
    public function escalateTask(CoreTask $task, ?string $notes = null): CoreTask
    {
        $fromStatus = $task->status;
        $task->status = 'escalated';
        $task->escalation_level = ((int) $task->escalation_level) + 1;
        $task->save();

        CoreTaskHistory::create([
            'task_id' => $task->id,
            'actor_id' => null,
            'action' => 'escalated',
            'from_status' => $fromStatus,
            'to_status' => 'escalated',
            'notes' => $notes ?? 'Automatic system escalation due to due date threshold',
        ]);

        return $task;
    }

    /**
     * Mengambil daftar tugas yang relevan untuk pengguna tertentu.
     *
     * @param  array{status?: string, priority?: string, module?: string}  $filters
     */
    public function getTasksForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CoreTask::query()->with('histories');

        if ($user->role !== 'admin') {
            $query->where(function (Builder $q) use ($user) {
                $q->where('assignee_user_id', $user->id)
                    ->orWhere('assignee_role', $user->role);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        return $query->orderBy('due_at', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
