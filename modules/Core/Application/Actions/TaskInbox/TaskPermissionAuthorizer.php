<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use App\Models\User;
use Modules\Core\Domain\Models\CoreTask;

/**
 * Service otorisasi izin tugas (Task RBAC & Permission Authorizer).
 */
class TaskPermissionAuthorizer
{
    /**
     * Memeriksa apakah pengguna berhak melihat rincian sebuah tugas.
     */
    public function canView(User $user, CoreTask $task): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($task->assignee_user_id === $user->id) {
            return true;
        }

        if ($task->assignee_role !== null && $task->assignee_role === $user->role) {
            return true;
        }

        return false;
    }

    /**
     * Memeriksa apakah pengguna berhak melakukan aksi mutasi (approve/reject/delegate).
     */
    public function canExecuteAction(User $user, CoreTask $task, string $action): bool
    {
        if (! $this->canView($user, $task)) {
            return false;
        }

        // Tugas yang sudah selesai atau dibatalkan tidak boleh dimutasi lagi
        if (in_array($task->status, ['approved', 'rejected', 'cancelled', 'completed'], true)) {
            return false;
        }

        if ($action === 'delegate') {
            // Hanya pemegang tugas atau manajer/admin yang boleh mendelegasikan
            return $user->role === 'admin' || in_array($user->role, ['manager', 'supervisor'], true) || $task->assignee_user_id === $user->id;
        }

        return true;
    }
}
