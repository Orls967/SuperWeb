<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Domain\Models\CoreTask;

/**
 * Service metrik performa operasional Kotak Masuk "Tugas Saya" (Task SLA & Throughput Analytics).
 */
class TaskPerformanceMetricService
{
    /**
     * Menghitung statistik KPI throughput dan rasio kepatuhan SLA.
     *
     * @return array{
     *     total_tasks: int,
     *     completed_tasks: int,
     *     completion_rate_pct: float,
     *     sla_breached_count: int,
     *     sla_compliance_rate_pct: float,
     *     median_turnaround_hours: float
     * }
     */
    public function computeMetrics(int $days = 30): array
    {
        $since = now()->subDays($days);
        $tasks = CoreTask::where('created_at', '>=', $since)->get();

        $total = $tasks->count();
        if ($total === 0) {
            return [
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'completion_rate_pct' => 100.0,
                'sla_breached_count' => 0,
                'sla_compliance_rate_pct' => 100.0,
                'median_turnaround_hours' => 0.0,
            ];
        }

        $completed = 0;
        $breached = 0;
        $turnaroundHours = [];
        $now = now();

        foreach ($tasks as $task) {
            $isDone = in_array($task->status, ['approved', 'rejected', 'completed'], true);
            if ($isDone) {
                $completed++;
                $hours = ($task->updated_at->getTimestamp() - $task->created_at->getTimestamp()) / 3600.0;
                $turnaroundHours[] = max(0.1, $hours);
            }

            // Evaluasi keterlambatan SLA
            if ($task->due_at !== null) {
                if ($isDone) {
                    if ($task->updated_at > $task->due_at) {
                        $breached++;
                    }
                } else {
                    if ($now > $task->due_at) {
                        $breached++;
                    }
                }
            }
        }

        $medianTurnaround = 0.0;
        if (! empty($turnaroundHours)) {
            sort($turnaroundHours);
            $mid = (int) floor(count($turnaroundHours) / 2);
            $medianTurnaround = round($turnaroundHours[$mid], 2);
        }

        $completionRate = round(($completed / $total) * 100.0, 2);
        $slaComplianceRate = round((($total - $breached) / $total) * 100.0, 2);

        return [
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'completion_rate_pct' => $completionRate,
            'sla_breached_count' => $breached,
            'sla_compliance_rate_pct' => $slaComplianceRate,
            'median_turnaround_hours' => $medianTurnaround,
        ];
    }
}
