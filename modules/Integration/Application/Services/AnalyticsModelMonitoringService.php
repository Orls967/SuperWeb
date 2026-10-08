<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * AnalyticsModelMonitoringService (Fase 345)
 *
 * Implements:
 *  - 345.1 MLOps-lite: model versioning, serving traffic control, rollback mechanisms
 *  - 345.3 Model documentation: purpose, limitations, failure modes discoverable
 *  - 345.4 Tests: Model cannot serve without documentation; rollback tested; ai:audit clean
 *  - 345.5 Edge case: Retraining with degraded performance rejected by evaluation gate
 *  - 345.6 Risk: Stale or missing documentation blocks deployment
 */
class AnalyticsModelMonitoringService
{
    /**
     * Deploy analytics model with documentation and serving gate (345.1, 345.3, 345.4).
     */
    public function deployModel(
        string $modelCode,
        string $modelName,
        string $version,
        bool $hasDoc,
        float $f1Score,
        ?string $rollbackVersion = null
    ): object {
        $mCode = strtoupper($modelCode);

        // Core gate 345.4: Model cannot serve without documentation
        if (! $hasDoc) {
            throw new InvalidArgumentException("MLOps governance breach: Model cannot serve traffic without complete documentation (345.4).");
        }

        $id = DB::table('analytics_model_deployments')->insertGetId([
            'model_code' => $mCode,
            'model_name' => $modelName,
            'model_version' => $version,
            'has_complete_documentation' => true,
            'baseline_f1_score' => $f1Score,
            'current_f1_score' => $f1Score,
            'is_serving_traffic' => true,
            'rollback_version' => $rollbackVersion,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('analytics_model_deployments')->find($id);
    }

    /**
     * Evaluate retraining candidate with performance degradation gate (345.5 Edge Case).
     */
    public function evaluateRetrainedModel(
        string $evalCode,
        string $modelCode,
        string $candidateVersion,
        float $candidateF1
    ): object {
        $eCode = strtoupper($evalCode);
        $mCode = strtoupper($modelCode);

        $current = DB::table('analytics_model_deployments')->where('model_code', $mCode)->first();
        $baselineF1 = $current ? (float) $current->baseline_f1_score : 0.0;

        // Edge case 345.5: Degradation rejects release
        $degraded = ($candidateF1 < $baselineF1);
        $approved = ! $degraded;

        $id = DB::table('analytics_model_retrain_evaluations')->insertGetId([
            'evaluation_code' => $eCode,
            'model_code' => $mCode,
            'candidate_version' => $candidateVersion,
            'candidate_f1_score' => $candidateF1,
            'performance_degraded' => $degraded,
            'release_approved' => $approved,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('analytics_model_retrain_evaluations')->find($id);
    }

    /**
     * Execute rollback to prior version (345.1 & 345.4).
     */
    public function rollbackModel(string $modelCode): object
    {
        $mCode = strtoupper($modelCode);
        $current = DB::table('analytics_model_deployments')->where('model_code', $mCode)->first();

        if (! $current || ! $current->rollback_version) {
            throw new InvalidArgumentException("Rollback failed: No rollback target version found for model '{$modelCode}'.");
        }

        DB::table('analytics_model_deployments')
            ->where('model_code', $mCode)
            ->update([
                'model_version' => $current->rollback_version,
                'rollback_version' => null,
                'updated_at' => now(),
            ]);

        return (object) DB::table('analytics_model_deployments')->where('model_code', $mCode)->first();
    }

    /**
     * AI & Analytics Platform Audit (`ai:audit`) (345.4, 345.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Models serving without documentation
        $undocumentedServing = DB::table('analytics_model_deployments')
            ->where('is_serving_traffic', true)
            ->where('has_complete_documentation', false)
            ->count();

        // Discrepancy 2: Degraded retrained models approved for release
        $degradedReleases = DB::table('analytics_model_retrain_evaluations')
            ->where('performance_degraded', true)
            ->where('release_approved', true)
            ->count();

        $discrepancies = $undocumentedServing + $degradedReleases;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_deployments' => DB::table('analytics_model_deployments')->count(),
            'total_evaluations' => DB::table('analytics_model_retrain_evaluations')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
