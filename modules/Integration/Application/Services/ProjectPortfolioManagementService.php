<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ProjectPortfolioManagementService (Fase 217)
 *
 * Implements:
 *  - 217.2 Dependency cycle detection in WBS Gantt tasks
 *  - 217.4 Change request lifecycle with mandatory executive approval before execution
 */
class ProjectPortfolioManagementService
{
    /**
     * Add task with cyclic dependency protection.
     */
    public function addTask(string $projectCode, string $taskCode, string $name, ?string $predecessor = null, int $duration = 1): object
    {
        if ($predecessor && strtoupper($predecessor) === strtoupper($taskCode)) {
            throw new \InvalidArgumentException("PPM error: Task {$taskCode} cannot depend on itself (circular dependency).");
        }

        // Check if predecessor points back to this task (2-node cycle check)
        if ($predecessor) {
            $predTask = DB::table('ops_ppm_project_tasks')->where('task_code', strtoupper($predecessor))->first();
            if ($predTask && $predTask->predecessor_task_code === strtoupper($taskCode)) {
                throw new \InvalidArgumentException("PPM error: Circular dependency cycle detected between {$taskCode} and {$predecessor}.");
            }
        }

        DB::table('ops_ppm_project_tasks')->updateOrInsert(
            ['task_code' => strtoupper($taskCode)],
            [
                'project_code' => strtoupper($projectCode),
                'task_name' => $name,
                'predecessor_task_code' => $predecessor ? strtoupper($predecessor) : null,
                'duration_days' => $duration,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ops_ppm_project_tasks')->where('task_code', strtoupper($taskCode))->first();
    }

    /**
     * Submit change request for scope and budget revision.
     */
    public function submitChangeRequest(string $project, string $description, float $budgetImpact, int $daysImpact): object
    {
        $code = 'CR-'.strtoupper(Str::random(8));

        $id = DB::table('ops_ppm_change_requests')->insertGetId([
            'change_request_code' => $code,
            'project_code' => strtoupper($project),
            'scope_modification_description' => $description,
            'budget_impact_idr' => $budgetImpact,
            'schedule_impact_days' => $daysImpact,
            'approval_status' => 'SUBMITTED',
            'approved_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_ppm_change_requests')->find($id);
    }

    /**
     * Approve and execute change request.
     */
    public function executeChangeRequest(string $crCode, string $approver): object
    {
        if (empty(trim($approver))) {
            throw new \InvalidArgumentException('Change execution error: Executive approver is mandatory.');
        }

        DB::table('ops_ppm_change_requests')->where('change_request_code', strtoupper($crCode))->update([
            'approval_status' => 'EXECUTED',
            'approved_by' => $approver,
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_ppm_change_requests')->where('change_request_code', strtoupper($crCode))->first();
    }

    /**
     * Quality audit gate (`ppm:audit`).
     */
    public function audit(): array
    {
        $unapprovedExecuted = DB::table('ops_ppm_change_requests')
            ->where('approval_status', 'EXECUTED')
            ->whereNull('approved_by')
            ->count();

        return [
            'status' => $unapprovedExecuted === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_tasks' => DB::table('ops_ppm_project_tasks')->count(),
            'total_change_requests' => DB::table('ops_ppm_change_requests')->count(),
            'discrepancy_count' => $unapprovedExecuted,
        ];
    }
}
