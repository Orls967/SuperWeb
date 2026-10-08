<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * EndToEndFinanceService (Fase 252)
 *
 * Implements:
 *  - 252.1 Unified finance process (plan, fund, transact, close, control, report, tax)
 *  - 252.2 Single source of truth: statutory, management, and ledger reconciliation
 *  - 252.3 Finance shared service operational SLA tracking & quality metrics
 *  - 252.5 Edge case: Management vs Statutory variance requires documented timing/estimation reconciliation
 *  - 252.6 Shared service SLA breaches automatically trigger capacity reviews
 *  - 252.7 Close task dependencies prevent premature cycle locking
 */
class EndToEndFinanceService
{
    /**
     * Initiate month-end close cycle with statutory vs management reconciliation check (252.1, 252.2, 252.5).
     */
    public function initiateCloseCycle(
        string $cycleCode,
        string $fiscalPeriod,
        float $ledgerNetIncomeUsd,
        float $statutoryNetIncomeUsd,
        float $managementNetIncomeUsd,
        ?string $varianceExplanation = null
    ): object {
        $variance = abs($managementNetIncomeUsd - $statutoryNetIncomeUsd);

        // Edge case 252.5: Any variance between management and statutory reporting must be documented
        if ($variance > 0.01 && empty($varianceExplanation)) {
            throw new InvalidArgumentException("Management vs Statutory variance of \${$variance} must have an explicit documented explanation (252.5).");
        }

        $code = strtoupper($cycleCode);

        $id = DB::table('finance_unified_close_cycles')->insertGetId([
            'cycle_code' => $code,
            'fiscal_period' => $fiscalPeriod,
            'ledger_net_income_usd' => $ledgerNetIncomeUsd,
            'statutory_net_income_usd' => $statutoryNetIncomeUsd,
            'management_net_income_usd' => $managementNetIncomeUsd,
            'reconciliation_variance_usd' => $variance,
            'reconciliation_explanation' => $varianceExplanation,
            'checklist_tasks_total' => 0,
            'checklist_tasks_completed' => 0,
            'is_cycle_locked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('finance_unified_close_cycles')->find($id);
    }

    /**
     * Add close checklist task with dependency mapping (252.1 & 252.7).
     */
    public function addCloseTask(int $cycleId, string $taskName, ?int $dependencyTaskId = null): object
    {
        $id = DB::table('finance_close_tasks')->insertGetId([
            'cycle_id' => $cycleId,
            'task_name' => strtoupper($taskName),
            'dependency_task_id' => $dependencyTaskId,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('finance_unified_close_cycles')
            ->where('id', $cycleId)
            ->increment('checklist_tasks_total');

        return (object) DB::table('finance_close_tasks')->find($id);
    }

    /**
     * Complete close task, enforcing dependency satisfaction (252.7).
     */
    public function completeCloseTask(int $taskId): object
    {
        $task = DB::table('finance_close_tasks')->find($taskId);
        if (! $task) {
            throw new InvalidArgumentException("Task #{$taskId} not found.");
        }

        // Dependency check (252.7)
        if ($task->dependency_task_id) {
            $dep = DB::table('finance_close_tasks')->find($task->dependency_task_id);
            if (! $dep || $dep->status !== 'COMPLETED') {
                throw new InvalidArgumentException("Cannot complete task '{$task->task_name}': Prerequisite task #{$task->dependency_task_id} is incomplete.");
            }
        }

        DB::table('finance_close_tasks')
            ->where('id', $taskId)
            ->update([
                'status' => 'COMPLETED',
                'updated_at' => now(),
            ]);

        DB::table('finance_unified_close_cycles')
            ->where('id', $task->cycle_id)
            ->increment('checklist_tasks_completed');

        return (object) DB::table('finance_close_tasks')->find($taskId);
    }

    /**
     * Lock financial close cycle after all checklist tasks are completed (252.1 & 252.4).
     */
    public function lockCloseCycle(int $cycleId): object
    {
        $cycle = DB::table('finance_unified_close_cycles')->find($cycleId);
        if (! $cycle) {
            throw new InvalidArgumentException("Cycle #{$cycleId} not found.");
        }

        $pendingTasks = DB::table('finance_close_tasks')
            ->where('cycle_id', $cycleId)
            ->where('status', '!=', 'COMPLETED')
            ->count();

        if ($pendingTasks > 0) {
            throw new InvalidArgumentException("Cannot lock close cycle: {$pendingTasks} checklist tasks are still pending completion.");
        }

        DB::table('finance_unified_close_cycles')
            ->where('id', $cycleId)
            ->update([
                'is_cycle_locked' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('finance_unified_close_cycles')->find($cycleId);
    }

    /**
     * Record finance shared service operational SLA metric (252.3 & 252.6).
     */
    public function recordSharedServiceSla(
        string $serviceStream,
        int $volumeProcessed,
        float $targetSlaHours,
        float $actualAvgHours
    ): object {
        $isBreached = ($actualAvgHours > $targetSlaHours);
        $code = 'SLA-'.strtoupper(Str::random(8));

        $id = DB::table('finance_shared_service_metrics')->insertGetId([
            'sla_code' => $code,
            'service_stream' => strtoupper($serviceStream),
            'volume_processed' => $volumeProcessed,
            'target_sla_hours' => $targetSlaHours,
            'actual_avg_hours' => $actualAvgHours,
            'is_sla_breached' => $isBreached,
            'capacity_review_triggered' => $isBreached, // 252.6 Edge case
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('finance_shared_service_metrics')->find($id);
    }

    /**
     * End-to-End Finance Platform Audit (`group:audit` & `enterprise:audit`) (252.4, 252.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Locked cycles where statutory income deviates from ledger truth
        $statutoryLedgerDeviations = DB::table('finance_unified_close_cycles')
            ->where('is_cycle_locked', true)
            ->whereRaw('ROUND(statutory_net_income_usd, 2) != ROUND(ledger_net_income_usd, 2)')
            ->count();

        // Discrepancy 2: Locked cycles with pending tasks
        $lockedWithIncompleteTasks = DB::table('finance_unified_close_cycles')
            ->where('is_cycle_locked', true)
            ->whereRaw('checklist_tasks_completed < checklist_tasks_total')
            ->count();

        // Discrepancy 3: Shared service SLA breaches without capacity review
        $unaddressedSlaBreaches = DB::table('finance_shared_service_metrics')
            ->where('is_sla_breached', true)
            ->where('capacity_review_triggered', false)
            ->count();

        $discrepancies = $statutoryLedgerDeviations + $lockedWithIncompleteTasks + $unaddressedSlaBreaches;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_cycles' => DB::table('finance_unified_close_cycles')->count(),
            'total_tasks' => DB::table('finance_close_tasks')->count(),
            'total_shared_service_metrics' => DB::table('finance_shared_service_metrics')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
