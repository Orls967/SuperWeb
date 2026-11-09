<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Algorithms;

use Modules\Platform\Domain\Enums\ChangeRiskTier;

final class ChangeRiskScorer
{
    /**
     * Critical core modules that have higher base impact weights.
     *
     * @var array<string, int>
     */
    private const MODULE_CRITICALITY = [
        'Core' => 100,
        'Banking' => 100,
        'Finance' => 100,
        'Treasury' => 95,
        'Payment' => 95,
        'Identity' => 90,
        'SupplyChain' => 85,
        'Logistics' => 80,
        'Hospital' => 80,
        'Platform' => 60,
        'AutoDex' => 40,
        'Mall' => 40,
    ];

    /**
     * Calculates deterministic risk score based on 4-dimension factors:
     * 1. Diff LOC weight (25%)
     * 2. Target module criticality (25%)
     * 3. Database DDL migration risk (35%)
     * 4. Historical failure/incident rate (15%)
     *
     * @param  array{loc_diff: int, target_module: string, has_ddl: bool, is_destructive_ddl: bool, past_incident_rate_pct?: int}  $params
     */
    public function calculateRisk(array $params): ChangeRiskAssessment
    {
        $locDiff = max(0, $params['loc_diff'] ?? 0);
        $targetModule = $params['target_module'] ?? 'Unknown';
        $hasDdl = (bool) ($params['has_ddl'] ?? false);
        $isDestructiveDdl = (bool) ($params['is_destructive_ddl'] ?? false);
        $pastIncidentRate = max(0, min(100, (int) ($params['past_incident_rate_pct'] ?? 0)));

        // Factor 1: LOC Diff (0..100) scaled at 1000 LOC = 100
        $locScore = min(100.0, ($locDiff / 1000.0) * 100.0);

        // Factor 2: Module Criticality (0..100)
        $modScore = (float) (self::MODULE_CRITICALITY[$targetModule] ?? 50);

        // Factor 3: DDL Risk (0..100)
        $ddlScore = 0.0;
        if ($hasDdl) {
            $ddlScore = $isDestructiveDdl ? 100.0 : 45.0;
        }

        // Factor 4: Historical Incident Rate (0..100)
        $histScore = (float) $pastIncidentRate;

        // Weighted sum (Formula: 0.25*loc + 0.25*mod + 0.35*ddl + 0.15*hist)
        $rawScore = (0.25 * $locScore) + (0.25 * $modScore) + (0.35 * $ddlScore) + (0.15 * $histScore);
        $finalScore = round(max(0.0, min(100.0, $rawScore)), 2);

        $tier = ChangeRiskTier::fromScore($finalScore);

        $factors = [
            'loc_diff' => $locDiff,
            'loc_score' => round($locScore, 2),
            'target_module' => $targetModule,
            'module_score' => round($modScore, 2),
            'has_ddl' => $hasDdl,
            'is_destructive_ddl' => $isDestructiveDdl,
            'ddl_score' => round($ddlScore, 2),
            'past_incident_rate_pct' => $pastIncidentRate,
            'historical_score' => round($histScore, 2),
        ];

        $payload = json_encode($factors, JSON_THROW_ON_ERROR).":{$finalScore}";
        $signature = hash('sha256', $payload);

        return new ChangeRiskAssessment($finalScore, $tier, $factors, $signature);
    }
}
