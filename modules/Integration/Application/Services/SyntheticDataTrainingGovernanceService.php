<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * SyntheticDataTrainingGovernanceService (Fase 359)
 *
 * Implements:
 *  - 359.1 Training data catalog with mandatory lineage tracking
 *  - 359.2 Synthetic dataset re-identification risk testing and privacy threshold enforcement
 *  - 359.4 Tests: Dataset without lineage blocked; synthetic data passes privacy threshold; ai:audit clean
 *  - 359.5 Edge case: Synthetic data failing privacy check is blocked from fixtures and regenerated with new seed
 *  - 359.6 Risk: Unrecorded dataset lineage blocked from training pipeline
 */
class SyntheticDataTrainingGovernanceService
{
    /**
     * Catalog training dataset with mandatory lineage verification (359.1, 359.4, 359.6 Risk).
     */
    public function catalogTrainingDataset(
        string $datasetCode,
        string $domainName,
        ?string $lineageHash = null
    ): object {
        $dCode = strtoupper($datasetCode);

        // Core gate 359.4 & 359.6 Risk: Datasets without lineage cannot enter training pipeline
        $hasLineage = ! empty($lineageHash);
        if (! $hasLineage) {
            throw new InvalidArgumentException("Training data governance violation: Dataset without recorded lineage is strictly blocked from training pipeline (359.6).");
        }

        $id = DB::table('training_data_catalog_datasets')->insertGetId([
            'dataset_code' => $dCode,
            'domain_name' => strtoupper($domainName),
            'lineage_hash' => $lineageHash,
            'has_recorded_lineage' => true,
            'permitted_for_training_pipeline' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('training_data_catalog_datasets')->find($id);
    }

    /**
     * Evaluate synthetic dataset privacy with re-identification risk check and regeneration on failure (359.2, 359.4, 359.5 Edge Case).
     */
    public function evaluateSyntheticDataPrivacy(
        string $evalCode,
        string $syntheticDatasetCode,
        float $reidentificationRisk,
        float $maxThreshold = 0.0500,
        bool $regeneratedWithNewSeed = false
    ): object {
        $eCode = strtoupper($evalCode);
        $sCode = strtoupper($syntheticDatasetCode);

        $passed = ($reidentificationRisk <= $maxThreshold);

        // Edge case 359.5: Failed privacy check blocks use for fixtures
        if (! $passed && ! $regeneratedWithNewSeed) {
            DB::table('synthetic_dataset_privacy_evaluations')->insert([
                'evaluation_code' => $eCode,
                'synthetic_dataset_code' => $sCode,
                'reidentification_risk_score' => $reidentificationRisk,
                'max_allowed_risk_threshold' => $maxThreshold,
                'privacy_check_passed' => false,
                'regenerated_with_new_seed' => false,
                'permitted_for_fixtures' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("Privacy threshold breach: Synthetic dataset re-identification risk ({$reidentificationRisk} > {$maxThreshold}) failed check and must be regenerated (359.5).");
        }

        $id = DB::table('synthetic_dataset_privacy_evaluations')->insertGetId([
            'evaluation_code' => $eCode,
            'synthetic_dataset_code' => $sCode,
            'reidentification_risk_score' => $reidentificationRisk,
            'max_allowed_risk_threshold' => $maxThreshold,
            'privacy_check_passed' => $passed,
            'regenerated_with_new_seed' => $regeneratedWithNewSeed,
            'permitted_for_fixtures' => $passed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('synthetic_dataset_privacy_evaluations')->find($id);
    }

    /**
     * AI Data & Privacy Governance Audit (`ai:audit`) (359.4, 359.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Datasets permitted for training without lineage
        $unlineagedPermitted = DB::table('training_data_catalog_datasets')
            ->where('permitted_for_training_pipeline', true)
            ->where('has_recorded_lineage', false)
            ->count();

        // Discrepancy 2: Synthetic datasets permitted for fixtures despite failing privacy check
        $leakySyntheticFixtures = DB::table('synthetic_dataset_privacy_evaluations')
            ->where('permitted_for_fixtures', true)
            ->where('privacy_check_passed', false)
            ->count();

        $discrepancies = $unlineagedPermitted + $leakySyntheticFixtures;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_training_datasets' => DB::table('training_data_catalog_datasets')->count(),
            'total_synthetic_evaluations' => DB::table('synthetic_dataset_privacy_evaluations')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
