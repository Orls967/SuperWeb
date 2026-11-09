<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Algorithms;

use Modules\Platform\Domain\Enums\CanaryRecommendation;

final class CanaryErrorEvaluator
{
    /**
     * Evaluates canary metrics against baseline using two-proportion hypothesis testing.
     *
     * @param  array{baseline_requests: int, baseline_errors: int, canary_requests: int, canary_errors: int}  $params
     */
    public function evaluate(array $params): CanaryEvaluationResult
    {
        $baseReq = max(1, $params['baseline_requests'] ?? 1);
        $baseErr = max(0, $params['baseline_errors'] ?? 0);
        $canReq = max(1, $params['canary_requests'] ?? 1);
        $canErr = max(0, $params['canary_errors'] ?? 0);

        $baseRate = (float) $baseErr / (float) $baseReq;
        $canRate = (float) $canErr / (float) $canReq;

        // Relative risk (ratio of canary error rate to baseline error rate)
        $relativeRisk = $baseRate > 0.0 ? ($canRate / $baseRate) : ($canRate > 0.0 ? 10.0 : 1.0);

        // Pooled sample proportion for standard error calculation
        $totalReq = $baseReq + $canReq;
        $totalErr = $baseErr + $canErr;
        $pooledP = (float) $totalErr / (float) $totalReq;

        $zScore = 0.0;
        if ($pooledP > 0.0 && $pooledP < 1.0) {
            $variance = $pooledP * (1.0 - $pooledP) * ((1.0 / $canReq) + (1.0 / $baseReq));
            $stdError = sqrt(max(1e-9, $variance));
            $zScore = ($canRate - $baseRate) / $stdError;
        }

        $recommendation = CanaryRecommendation::CONTINUE;
        $summary = 'Lalu lintas canary stabil dalam batas deviasi statistik standar.';

        // Critical threshold: Error rate >= 3.0x baseline OR Z-Score >= 3.0 with sufficient sample size
        if (($canRate >= 3.0 * $baseRate && $canErr >= 5) || $zScore >= 3.0) {
            $recommendation = CanaryRecommendation::ROLLBACK;
            $summary = "Anomali kritis: Tingkat galat canary ({$canRate}) melonjak signifikan melampaui baseline ({$baseRate}), Z-Score: {$zScore}. Rekomendasi auto-rollback segera.";
        } elseif ($canRate <= $baseRate && $canReq >= 5000 && $zScore < 1.0) {
            $recommendation = CanaryRecommendation::PROMOTE;
            $summary = 'Canary terbukti andal dengan deviasi nihil. Rekomendasi promosi ke 100% lalu lintas produksi.';
        }

        return new CanaryEvaluationResult(
            round($baseRate, 6),
            round($canRate, 6),
            round($relativeRisk, 4),
            round($zScore, 3),
            $recommendation,
            $summary
        );
    }
}
