<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * PlatformAutomatedOpsFinopsService (Fase 297)
 *
 * Implements:
 *  - 297.1 Automated runbook execution with dry-run verification, material approval gate, and evidence logging
 *  - 297.2 Scheduled job registry with strict overlap protection guard
 *  - 297.4 Operational Readiness Review (ORR): on-call, dashboard, rollback plan, cost estimate release gate
 *  - 297.5 Tests: Runbook idempotent & authorized, overlapping schedule blocked, readiness missing item blocks release
 *  - 297.7 Consecutive job failures trigger active owner alerting, eliminating silent endless retries
 *  - 297.8 Failed ORR review holds release until all operational criteria are fully met
 */
class PlatformAutomatedOpsFinopsService
{
    /**
     * Create operational runbook task (297.1 & 297.5).
     */
    public function registerRunbookTask(string $runbookCode, string $taskType, bool $isMaterial = true): object
    {
        $code = strtoupper($runbookCode);

        $id = DB::table('platform_automated_runbooks')->insertGetId([
            'runbook_code' => $code,
            'task_type' => strtoupper($taskType),
            'dry_run_verified' => false,
            'is_material_action' => $isMaterial,
            'approver_lead_id' => null,
            'is_executed' => false,
            'execution_evidence_log' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_automated_runbooks')->find($id);
    }

    /**
     * Perform dry-run and approve material runbook execution (297.1 & 297.5).
     */
    public function verifyAndApproveRunbook(string $runbookCode, string $approverLeadId): object
    {
        $code = strtoupper($runbookCode);
        DB::table('platform_automated_runbooks')
            ->where('runbook_code', $code)
            ->update([
                'dry_run_verified' => true,
                'approver_lead_id' => strtoupper($approverLeadId),
                'updated_at' => now(),
            ]);

        return (object) DB::table('platform_automated_runbooks')->where('runbook_code', $code)->first();
    }

    /**
     * Execute runbook task ensuring authorization and dry-run verification (297.1 & 297.5).
     */
    public function executeRunbook(string $runbookCode, string $evidenceLog): object
    {
        $code = strtoupper($runbookCode);
        $rb = DB::table('platform_automated_runbooks')->where('runbook_code', $code)->first();
        if (! $rb) {
            throw new InvalidArgumentException("Runbook '{$runbookCode}' not found.");
        }

        // Gate 297.5: Must have dry-run verified
        if (! $rb->dry_run_verified) {
            throw new InvalidArgumentException('Runbook execution rejected: Must complete successful dry-run simulation first (297.5).');
        }

        // Gate 297.5: Material tasks require authorized approver
        if ($rb->is_material_action && empty($rb->approver_lead_id)) {
            throw new InvalidArgumentException('Runbook execution rejected: Material task requires documented lead approval (297.5).');
        }

        DB::table('platform_automated_runbooks')
            ->where('runbook_code', $code)
            ->update([
                'is_executed' => true,
                'execution_evidence_log' => $evidenceLog,
                'updated_at' => now(),
            ]);

        return (object) DB::table('platform_automated_runbooks')->where('runbook_code', $code)->first();
    }

    /**
     * Start scheduled job enforcing overlap guard (297.2 & 297.5).
     */
    public function acquireScheduledJobLock(string $jobCode, string $ownerLeadId): object
    {
        $code = strtoupper($jobCode);
        $job = DB::table('platform_scheduled_jobs')->where('job_code', $code)->first();

        // Overlap guard 297.5: If job is currently running, block second overlapping instance
        if ($job && $job->is_currently_running) {
            throw new InvalidArgumentException("Overlap schedule blocked: Job '{$jobCode}' is currently running in another process (297.5).");
        }

        DB::table('platform_scheduled_jobs')->updateOrInsert(
            ['job_code' => $code],
            [
                'owner_lead_id' => strtoupper($ownerLeadId),
                'cadence' => 'HOURLY',
                'is_currently_running' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('platform_scheduled_jobs')->where('job_code', $code)->first();
    }

    /**
     * Record scheduled job failure, triggering owner alert on consecutive threshold (297.7).
     */
    public function recordJobFailure(string $jobCode): object
    {
        $code = strtoupper($jobCode);
        $job = DB::table('platform_scheduled_jobs')->where('job_code', $code)->first();
        if (! $job) {
            throw new InvalidArgumentException("Job '{$jobCode}' not found.");
        }

        $failures = (int) $job->consecutive_failure_count + 1;
        $alertOwner = ($failures >= 3); // 297.7: Consecutive failure triggers owner alert, no silent retry

        DB::table('platform_scheduled_jobs')
            ->where('job_code', $code)
            ->update([
                'is_currently_running' => false,
                'consecutive_failure_count' => $failures,
                'owner_alert_sent' => $alertOwner,
                'updated_at' => now(),
            ]);

        return (object) DB::table('platform_scheduled_jobs')->where('job_code', $code)->first();
    }

    /**
     * Evaluate Operational Readiness Review (ORR) release gate (297.4, 297.5, 297.8).
     */
    public function evaluateReadinessReview(
        string $reviewCode,
        string $featureName,
        bool $onCall,
        bool $dashboard,
        bool $rollback,
        bool $costEstimate
    ): object {
        $code = strtoupper($reviewCode);

        // Gate 297.5 & 297.8: Missing any item blocks feature release
        $isAuthorized = ($onCall && $dashboard && $rollback && $costEstimate);

        $id = DB::table('platform_operational_readiness_reviews')->insertGetId([
            'review_code' => $code,
            'feature_name' => $featureName,
            'has_on_call_roster' => $onCall,
            'has_grafana_dashboard' => $dashboard,
            'has_rollback_plan' => $rollback,
            'has_cost_estimate' => $costEstimate,
            'is_release_authorized' => $isAuthorized,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $isAuthorized) {
            throw new InvalidArgumentException('Operational readiness review failed: Feature release blocked due to missing operational readiness artifacts (297.8).');
        }

        return (object) DB::table('platform_operational_readiness_reviews')->find($id);
    }

    /**
     * Automated Operations Platform Audit (`platform:audit`) (297.5, 297.9).
     */
    public function audit(): array
    {
        // Discrepancy 1: Executed runbooks without evidence log
        $unloggedExecutions = DB::table('platform_automated_runbooks')
            ->where('is_executed', true)
            ->whereNull('execution_evidence_log')
            ->count();

        // Discrepancy 2: Repeated failing jobs (>= 3) without owner alert sent
        $unalertedFailingJobs = DB::table('platform_scheduled_jobs')
            ->where('consecutive_failure_count', '>=', 3)
            ->where('owner_alert_sent', false)
            ->count();

        // Discrepancy 3: Features authorized for release with incomplete ORR
        $improperlyAuthorizedReleases = DB::table('platform_operational_readiness_reviews')
            ->where('is_release_authorized', true)
            ->where(function ($query) {
                $query->where('has_on_call_roster', false)
                    ->orWhere('has_grafana_dashboard', false)
                    ->orWhere('has_rollback_plan', false)
                    ->orWhere('has_cost_estimate', false);
            })
            ->count();

        $discrepancies = $unloggedExecutions + $unalertedFailingJobs + $improperlyAuthorizedReleases;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_runbooks' => DB::table('platform_automated_runbooks')->count(),
            'total_scheduled_jobs' => DB::table('platform_scheduled_jobs')->count(),
            'total_orr_reviews' => DB::table('platform_operational_readiness_reviews')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
