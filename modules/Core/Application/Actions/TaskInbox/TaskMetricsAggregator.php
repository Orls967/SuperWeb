<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use Modules\Core\Domain\Models\CoreTask;

/**
 * Service pengagregasi metrik beban tugas per peran dan departemen (Workload Metrics Aggregator).
 */
class TaskMetricsAggregator
{
    /**
     * Menghitung distribusi matriks beban tugas aktif per peran dan prioritas.
     *
     * @return array<string, array<string, int>> [role => [urgent => cnt, high => cnt, normal => cnt, low => cnt]]
     */
    public function aggregateRoleMatrix(): array
    {
        $activeTasks = CoreTask::whereIn('status', ['pending', 'in_progress', 'delegated', 'escalated'])->get();
        $matrix = [];

        foreach ($activeTasks as $task) {
            $role = (string) ($task->assignee_role ?? 'unassigned');
            $prio = strtolower((string) ($task->priority ?? 'normal'));

            if (! isset($matrix[$role])) {
                $matrix[$role] = ['urgent' => 0, 'high' => 0, 'normal' => 0, 'low' => 0];
            }

            $matrix[$role][$prio] = ($matrix[$role][$prio] ?? 0) + 1;
        }

        return $matrix;
    }
}
