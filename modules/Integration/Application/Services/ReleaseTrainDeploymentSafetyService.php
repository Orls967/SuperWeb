<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * ReleaseTrainDeploymentSafetyService (Fase 238)
 *
 * Implements:
 *  - 238.1 Scheduled release train & dedicated hotfix approval pathway with automated notes
 *  - 238.2 Change advisory: blast radius risk score, CAB evaluation, mandatory rollback plan
 *  - 238.3 Schema migration safety: expand-contract enforcement, idempotent linter
 *  - 238.4 Canary & blue-green simulation: traffic ramping, automated rollback on error spikes
 *  - 238.6 Edge case: Urgent hotfix separate approval + mandatory post-merge review
 *  - 238.7 Data rollback safety: expand-contract pattern enforcement for populated schemas
 */
class ReleaseTrainDeploymentSafetyService
{
    /**
     * Schedule release train or urgent hotfix (238.1 & 238.6).
     */
    public function scheduleReleaseTrain(
        string $releaseType = 'SCHEDULED',
        ?string $approvedBy = null,
        ?string $notesSummary = null
    ): object {
        $type = strtoupper($releaseType);
        if ($type === 'HOTFIX' && empty($approvedBy)) {
            throw new InvalidArgumentException('Hotfix requires separate explicit emergency approver (238.6).');
        }

        $code = ($type === 'HOTFIX' ? 'HOTFIX-' : 'TRAIN-').strtoupper(Str::random(8));

        $id = DB::table('platform_release_trains')->insertGetId([
            'train_code' => $code,
            'release_type' => $type,
            'status' => 'SCHEDULED',
            'scheduled_at' => now(),
            'release_notes_summary' => $notesSummary ?? "Release {$code} notes automatically generated.",
            'approved_by' => $approvedBy,
            'post_merge_review_completed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_release_trains')->find($id);
    }

    /**
     * Complete post-merge review for hotfixes (238.6 Edge Case).
     */
    public function completePostMergeReview(int $trainId, string $reviewer): object
    {
        $train = DB::table('platform_release_trains')->find($trainId);
        if (! $train) {
            throw new InvalidArgumentException("Release train #{$trainId} not found.");
        }

        DB::table('platform_release_trains')
            ->where('id', $trainId)
            ->update([
                'post_merge_review_completed' => true,
                'status' => 'COMPLETED',
                'updated_at' => now(),
            ]);

        return (object) DB::table('platform_release_trains')->find($trainId);
    }

    /**
     * Evaluate Change Request with blast radius & risk score (238.2).
     */
    public function evaluateChangeRequest(
        string $title,
        string $blastRadiusType,
        float $riskScore,
        bool $hasRollbackPlan
    ): object {
        $blastRadius = strtoupper($blastRadiusType);
        $needsCab = ($riskScore >= 70.0 || in_array($blastRadius, ['MONEY', 'PII', 'AVAILABILITY'], true));

        $cabStatus = 'NOT_REQUIRED';
        if ($needsCab) {
            $cabStatus = 'PENDING';
        }

        $code = 'CR-'.strtoupper(Str::random(8));

        $id = DB::table('platform_change_requests')->insertGetId([
            'cr_code' => $code,
            'title' => $title,
            'blast_radius_type' => $blastRadius,
            'risk_score' => $riskScore,
            'cab_approval_status' => $cabStatus,
            'rollback_plan_documented' => $hasRollbackPlan,
            'post_deploy_verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_change_requests')->find($id);
    }

    /**
     * Approve change request by Change Advisory Board (CAB) (238.2).
     */
    public function approveCab(int $crId, string $cabApprover): object
    {
        $cr = DB::table('platform_change_requests')->find($crId);
        if (! $cr) {
            throw new InvalidArgumentException("Change request #{$crId} not found.");
        }

        if (! $cr->rollback_plan_documented) {
            throw new InvalidArgumentException('Cannot approve CAB: Mandatory rollback plan is missing (238.2).');
        }

        DB::table('platform_change_requests')
            ->where('id', $crId)
            ->update([
                'cab_approval_status' => 'APPROVED',
                'updated_at' => now(),
            ]);

        return (object) DB::table('platform_change_requests')->find($crId);
    }

    /**
     * Mark change request verified post-deployment (238.2).
     */
    public function verifyPostDeploy(int $crId): object
    {
        DB::table('platform_change_requests')
            ->where('id', $crId)
            ->update([
                'post_deploy_verified' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('platform_change_requests')->find($crId);
    }

    /**
     * Safety linter for database schema migrations (238.3 & 238.7).
     */
    public function lintSchemaMigration(
        string $migrationName,
        string $pattern,
        bool $isBreakingChange,
        bool $hasSpecialApproval = false
    ): object {
        $patternUpper = strtoupper($pattern);
        $rejectionReason = null;
        $passed = true;

        // Breaking migrations must use expand-contract pattern unless specifically approved
        if ($isBreakingChange && $patternUpper !== 'EXPAND_CONTRACT' && ! $hasSpecialApproval) {
            $passed = false;
            $rejectionReason = 'Breaking migration rejected: Populated schema requires expand-contract pattern (238.3 & 238.7).';
        }

        $id = DB::table('platform_schema_migrations_safety')->insertGetId([
            'migration_name' => $migrationName,
            'migration_pattern' => $patternUpper,
            'is_breaking_change' => $isBreakingChange,
            'has_lint_passed' => $passed,
            'rejection_reason' => $rejectionReason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_schema_migrations_safety')->find($id);
    }

    /**
     * Canary deployment simulation with automated rollback on error spikes (238.4 & 238.5).
     */
    public function simulateCanaryRollout(
        string $rolloutCode,
        ?int $trainId,
        float $trafficPct,
        float $errorRatePct,
        float $errorThresholdPct = 5.0
    ): object {
        $status = 'IN_PROGRESS';
        $rolledBackAt = null;

        if ($errorRatePct > $errorThresholdPct) {
            $status = 'AUTO_ROLLED_BACK';
            $rolledBackAt = now();
        } elseif ($trafficPct >= 100.0) {
            $status = 'PROMOTED';
        }

        $id = DB::table('platform_canary_rollouts')->insertGetId([
            'rollout_code' => $rolloutCode,
            'release_train_id' => $trainId,
            'current_traffic_pct' => $trafficPct,
            'error_rate_pct' => $errorRatePct,
            'status' => $status,
            'rolled_back_at' => $rolledBackAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_canary_rollouts')->find($id);
    }

    /**
     * Deployment Safety & Platform Audit (`platform:audit`) (238.5).
     */
    public function audit(): array
    {
        // Discrepancy 1: Breaking migration with lint passed without expand-contract
        $invalidMigrations = DB::table('platform_schema_migrations_safety')
            ->where('is_breaking_change', true)
            ->where('has_lint_passed', true)
            ->where('migration_pattern', '!=', 'EXPAND_CONTRACT')
            ->count();

        // Discrepancy 2: Canary rollout with high error rate (> 5%) that is not rolled back
        $uncontainedCanaryErrors = DB::table('platform_canary_rollouts')
            ->where('error_rate_pct', '>', 5.0)
            ->where('status', '!=', 'AUTO_ROLLED_BACK')
            ->count();

        // Discrepancy 3: Hotfixes marked completed without post merge review
        $unreviewedHotfixes = DB::table('platform_release_trains')
            ->where('release_type', 'HOTFIX')
            ->where('status', 'COMPLETED')
            ->where('post_merge_review_completed', false)
            ->count();

        // Discrepancy 4: High risk change requests approved without rollback plan
        $unplannedChanges = DB::table('platform_change_requests')
            ->where('cab_approval_status', 'APPROVED')
            ->where('rollback_plan_documented', false)
            ->count();

        $discrepancies = $invalidMigrations + $uncontainedCanaryErrors + $unreviewedHotfixes + $unplannedChanges;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_trains' => DB::table('platform_release_trains')->count(),
            'total_change_requests' => DB::table('platform_change_requests')->count(),
            'total_migrations_audited' => DB::table('platform_schema_migrations_safety')->count(),
            'total_canary_rollouts' => DB::table('platform_canary_rollouts')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
