<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DeveloperExperienceQualityService (Fase 237)
 *
 * Implements:
 *  - 237.1 Developer portal sandbox provisioning & domain slicing
 *  - 237.2 Quality gates CI/CD (Pint, PHPStan max, security audit, mutation testing)
 *  - 237.3 Synthetic test data factory with deterministic masking (zero real PII)
 *  - 237.6 Edge case: Stale sandboxes (> 7 days inactive) auto-reclaimed to conserve resources
 *  - 237.7 Test data masking strictly enforced when importing simulated data to staging
 */
class DeveloperExperienceQualityService
{
    /**
     * Provision developer sandbox environment (237.1).
     */
    public function provisionSandbox(
        string $developerId,
        string $environmentType,
        string $domainSlice
    ): object {
        $code = 'SBX-'.strtoupper(Str::random(8));

        $id = DB::table('platform_dev_sandboxes')->insertGetId([
            'sandbox_code' => $code,
            'developer_id' => strtoupper($developerId),
            'environment_type' => strtoupper($environmentType),
            'domain_slice' => strtoupper($domainSlice),
            'status' => 'ACTIVE',
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_dev_sandboxes')->find($id);
    }

    /**
     * Auto-reclaim stale sandboxes (> 7 days without activity) (237.6 Edge Case).
     */
    public function reclaimStaleSandboxes(int $staleThresholdDays = 7): int
    {
        $cutoff = now()->subDays($staleThresholdDays);

        $reclaimedCount = DB::table('platform_dev_sandboxes')
            ->where('status', 'ACTIVE')
            ->where('last_activity_at', '<', $cutoff)
            ->update([
                'status' => 'RECLAIMED',
                'updated_at' => now(),
            ]);

        return $reclaimedCount;
    }

    /**
     * Evaluate CI/CD quality gate enforcement (237.2 & 237.5).
     */
    public function evaluateQualityGate(
        string $commitHash,
        bool $lintPassed,
        bool $staticAnalysisPassed,
        bool $securityAuditPassed,
        float $mutationScorePct = 85.00
    ): object {
        $rejectionReasons = [];

        if (! $lintPassed) {
            $rejectionReasons[] = 'Code style linting (Pint) failed';
        }
        if (! $staticAnalysisPassed) {
            $rejectionReasons[] = 'Static analysis (PHPStan Level Max) failed';
        }
        if (! $securityAuditPassed) {
            $rejectionReasons[] = 'Dependency security audit vulnerability detected';
        }
        if ($mutationScorePct < 80.00) {
            $rejectionReasons[] = "Mutation testing score ({$mutationScorePct}%) is below the mandatory 80% threshold";
        }

        $passed = empty($rejectionReasons);
        $verdict = $passed ? 'PASSED' : 'REJECTED';
        $reason = $passed ? null : implode('; ', $rejectionReasons);

        $code = 'GATE-'.strtoupper(Str::random(8));

        $id = DB::table('platform_ci_quality_gates')->insertGetId([
            'gate_run_code' => $code,
            'commit_hash' => $commitHash,
            'lint_passed' => $lintPassed,
            'static_analysis_passed' => $staticAnalysisPassed,
            'security_audit_passed' => $securityAuditPassed,
            'mutation_score_pct' => $mutationScorePct,
            'gate_verdict' => $verdict,
            'rejection_reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_ci_quality_gates')->find($id);
    }

    /**
     * Generate synthetic test data with deterministic PII masking (237.3 & 237.7).
     */
    public function generateSyntheticDataSeed(
        string $businessLine,
        string $recordType,
        string $rawPiiValue,
        string $seedKey
    ): object {
        // Deterministic masking (237.7)
        $maskedId = 'SYNTH-'.strtoupper(substr(hash('sha256', $seedKey.'_'.$rawPiiValue), 0, 10));
        $maskedSample = [
            'synthetic_subject_id' => $maskedId,
            'business_line' => strtoupper($businessLine),
            'record_type' => strtoupper($recordType),
            'masked_pii_token' => 'MASKED_'.substr(hash('sha256', $rawPiiValue), 0, 8),
            'is_synthetic' => true,
        ];

        $code = 'SEED-'.strtoupper(Str::random(8));

        $id = DB::table('platform_synthetic_data_seeds')->insertGetId([
            'seed_code' => $code,
            'business_line' => strtoupper($businessLine),
            'record_type' => strtoupper($recordType),
            'is_pii_masked' => true,
            'deterministic_seed_key' => $seedKey,
            'masked_sample_json' => json_encode($maskedSample),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_synthetic_data_seeds')->find($id);
    }

    /**
     * Quality audit gate (`platform:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: CI Gates marked PASSED when lint, SA, or security failed
        $falsePassingGates = DB::table('platform_ci_quality_gates')
            ->where('gate_verdict', 'PASSED')
            ->where(function ($q) {
                $q->where('lint_passed', false)
                  ->orWhere('static_analysis_passed', false)
                  ->orWhere('security_audit_passed', false)
                  ->orWhere('mutation_score_pct', '<', 80.0);
            })
            ->count();

        // Discrepancy 2: Synthetic data records with unmasked PII
        $unmaskedData = DB::table('platform_synthetic_data_seeds')
            ->where('is_pii_masked', false)
            ->count();

        // Discrepancy 3: Active sandboxes exceeding 7 days inactive without reclaim
        $staleActiveSandboxes = DB::table('platform_dev_sandboxes')
            ->where('status', 'ACTIVE')
            ->where('last_activity_at', '<', now()->subDays(7))
            ->count();

        $discrepancies = $falsePassingGates + $unmaskedData + $staleActiveSandboxes;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_sandboxes' => DB::table('platform_dev_sandboxes')->count(),
            'total_ci_gates' => DB::table('platform_ci_quality_gates')->count(),
            'total_synthetic_seeds' => DB::table('platform_synthetic_data_seeds')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
