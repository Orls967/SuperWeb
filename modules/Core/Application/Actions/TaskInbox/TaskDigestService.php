<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use Modules\Core\Domain\Models\CoreTask;

/**
 * Service pembuatan ringkasan harian/mingguan (digest) beban kerja tugas pengguna.
 */
class TaskDigestService
{
    /**
     * Menghasilkan statistik digest tugas aktif untuk pengguna atau keseluruhan sistem.
     *
     * @return array{
     *     pending_count: int,
     *     urgent_count: int,
     *     escalated_count: int,
     *     overdue_count: int,
     *     by_module: array<string, int>
     * }
     */
    public function generateDigestSummary(?string $userId = null): array
    {
        $query = CoreTask::query()->whereIn('status', ['pending', 'in_progress', 'delegated', 'escalated']);

        if ($userId !== null) {
            $query->where('assignee_user_id', $userId);
        }

        $allActive = $query->get();

        $pendingCount = 0;
        $urgentCount = 0;
        $escalatedCount = 0;
        $overdueCount = 0;
        $byModule = [];
        $now = now();

        foreach ($allActive as $task) {
            if ($task->status === 'pending') {
                $pendingCount++;
            }
            if ($task->priority === 'urgent') {
                $urgentCount++;
            }
            if ($task->status === 'escalated' || $task->escalation_level > 0) {
                $escalatedCount++;
            }
            if ($task->due_at !== null && $task->due_at < $now) {
                $overdueCount++;
            }

            $mod = $task->module ?: 'General';
            $byModule[$mod] = ($byModule[$mod] ?? 0) + 1;
        }

        return [
            'pending_count' => $pendingCount,
            'urgent_count' => $urgentCount,
            'escalated_count' => $escalatedCount,
            'overdue_count' => $overdueCount,
            'by_module' => $byModule,
        ];
    }
}
