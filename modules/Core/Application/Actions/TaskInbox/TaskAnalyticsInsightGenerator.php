<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\CoreTask;

final class TaskAnalyticsInsightGenerator
{
    /**
     * Menghasilkan wawasan analitik mendalam dari tren penyelesaian tugas dan SLA.
     *
     * @return array<string, mixed>
     */
    public function generateInsights(int $lookbackDays = 30): array
    {
        $since = Carbon::now()->subDays($lookbackDays);
        $tasks = CoreTask::where('created_at', '>=', $since)->get();

        $velocity = $this->calculateVelocity($tasks, $lookbackDays);
        $bottlenecks = $this->identifyBottlenecks($tasks);
        $riskProjections = $this->forecastRisk($tasks);
        $moduleAdherence = $this->analyzeModuleAdherence($tasks);

        return [
            'period_days' => $lookbackDays,
            'analyzed_tasks_count' => $tasks->count(),
            'velocity' => $velocity,
            'bottlenecks' => $bottlenecks,
            'risk_projections' => $riskProjections,
            'module_adherence' => $moduleAdherence,
            'overall_health_score' => $this->computeHealthScore($velocity, $riskProjections),
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, CoreTask>  $tasks
     * @return array<string, mixed>
     */
    private function calculateVelocity(Collection $tasks, int $days): array
    {
        $completed = $tasks->whereIn('status', ['approved', 'completed', 'resolved']);
        $completedCount = $completed->count();
        $dailyRate = $days > 0 ? round($completedCount / $days, 2) : 0.0;

        $turnarounds = [];
        foreach ($completed as $task) {
            if ($task->created_at !== null && $task->updated_at !== null) {
                $turnarounds[] = $task->created_at->diffInHours($task->updated_at);
            }
        }

        $avgTurnaroundHours = count($turnarounds) > 0 ? round(array_sum($turnarounds) / count($turnarounds), 1) : 0.0;

        return [
            'completed_count' => $completedCount,
            'tasks_per_day' => $dailyRate,
            'average_turnaround_hours' => $avgTurnaroundHours,
            'throughput_rating' => $dailyRate >= 10.0 ? 'optimal' : ($dailyRate >= 3.0 ? 'normal' : 'sluggish'),
        ];
    }

    /**
     * @param  Collection<int, CoreTask>  $tasks
     * @return list<array<string, mixed>>
     */
    private function identifyBottlenecks(Collection $tasks): array
    {
        $pending = $tasks->whereIn('status', ['pending', 'escalated']);
        $byModule = $pending->groupBy('module_code');

        $bottlenecks = [];
        foreach ($byModule as $moduleCode => $moduleTasks) {
            $overdueCount = $moduleTasks->filter(function (CoreTask $task): bool {
                return $task->due_date !== null && Carbon::parse($task->due_date)->isPast();
            })->count();

            if ($overdueCount > 0 || $moduleTasks->count() >= 5) {
                $bottlenecks[] = [
                    'module_code' => $moduleCode,
                    'pending_tasks' => $moduleTasks->count(),
                    'overdue_tasks' => $overdueCount,
                    'severity' => $overdueCount >= 3 ? 'critical' : 'warning',
                ];
            }
        }

        usort($bottlenecks, static fn (array $a, array $b): int => $b['overdue_tasks'] <=> $a['overdue_tasks']);

        return $bottlenecks;
    }

    /**
     * @param  Collection<int, CoreTask>  $tasks
     * @return array<string, mixed>
     */
    private function forecastRisk(Collection $tasks): array
    {
        $openTasks = $tasks->whereIn('status', ['pending', 'queued', 'escalated']);
        $atRiskCount = 0;
        $now = Carbon::now();

        foreach ($openTasks as $task) {
            if ($task->due_date !== null) {
                $due = Carbon::parse($task->due_date);
                if ($due->isPast() || $due->diffInHours($now) <= 12) {
                    $atRiskCount++;
                }
            }
        }

        $totalOpen = $openTasks->count();
        $riskRatio = $totalOpen > 0 ? round(($atRiskCount / $totalOpen) * 100, 1) : 0.0;

        return [
            'open_tasks_count' => $totalOpen,
            'at_risk_count' => $atRiskCount,
            'risk_ratio_pct' => $riskRatio,
            'alert_level' => $riskRatio > 30.0 ? 'high' : ($riskRatio > 10.0 ? 'medium' : 'low'),
        ];
    }

    /**
     * @param  Collection<int, CoreTask>  $tasks
     * @return array<string, array<string, int|float>>
     */
    private function analyzeModuleAdherence(Collection $tasks): array
    {
        $grouped = $tasks->groupBy('module_code');
        $adherence = [];

        foreach ($grouped as $module => $moduleTasks) {
            $total = $moduleTasks->count();
            $breached = $moduleTasks->filter(function (CoreTask $t): bool {
                return $t->status === 'escalated' || ($t->due_date !== null && Carbon::parse($t->due_date)->isPast() && $t->status === 'pending');
            })->count();

            $rate = $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100.0;

            $adherence[$module] = [
                'total' => $total,
                'breached' => $breached,
                'compliance_rate_pct' => $rate,
            ];
        }

        return $adherence;
    }

    /**
     * @param  array<string, mixed>  $velocity
     * @param  array<string, mixed>  $risk
     */
    private function computeHealthScore(array $velocity, array $risk): int
    {
        $score = 100;
        $riskPct = (float) ($risk['risk_ratio_pct'] ?? 0.0);
        $turnaround = (float) ($velocity['average_turnaround_hours'] ?? 0.0);

        $score -= (int) min(50, $riskPct * 1.2);
        if ($turnaround > 48.0) {
            $score -= 20;
        } elseif ($turnaround > 24.0) {
            $score -= 10;
        }

        return max(10, min(100, $score));
    }
}
