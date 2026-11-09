<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

final class CanaryStatisticalHypothesisRunner
{
    /**
     * @return array{
     *     hypothesis_confirmed: bool,
     *     regression_detected: bool,
     *     z_score: float,
     *     p_value: float,
     *     confidence_interval: array{lower: float, upper: float},
     *     recommendation: string
     * }
     */
    public function evaluateProportionDifference(
        int $baselineSuccesses,
        int $baselineTotal,
        int $canarySuccesses,
        int $canaryTotal,
        float $significanceLevel = 0.05
    ): array {
        if ($baselineTotal <= 0 || $canaryTotal <= 0) {
            return [
                'hypothesis_confirmed' => false,
                'regression_detected' => false,
                'z_score' => 0.0,
                'p_value' => 1.0,
                'confidence_interval' => ['lower' => 0.0, 'upper' => 0.0],
                'recommendation' => 'INSUFFICIENT_SAMPLE_SIZE',
            ];
        }

        $p1 = $baselineSuccesses / $baselineTotal;
        $p2 = $canarySuccesses / $canaryTotal;

        // Pooled sample proportion
        $pPool = ($baselineSuccesses + $canarySuccesses) / ($baselineTotal + $canaryTotal);

        // Standard error under null hypothesis
        $se = sqrt($pPool * (1.0 - $pPool) * ((1.0 / $baselineTotal) + (1.0 / $canaryTotal)));

        if ($se <= 0.0000001) {
            return [
                'hypothesis_confirmed' => true,
                'regression_detected' => false,
                'z_score' => 0.0,
                'p_value' => 1.0,
                'confidence_interval' => ['lower' => round($p2 - $p1, 4), 'upper' => round($p2 - $p1, 4)],
                'recommendation' => 'NO_DIFFERENCE',
            ];
        }

        $diff = $p2 - $p1;
        $z = $diff / $se;

        // Approximate two-tailed p-value using normal distribution
        $pValue = 2.0 * (1.0 - $this->standardNormalCdf(abs($z)));

        // 95% Confidence interval for difference in proportions
        $seDiff = sqrt((($p1 * (1.0 - $p1)) / $baselineTotal) + (($p2 * (1.0 - $p2)) / $canaryTotal));
        $margin = 1.96 * $seDiff;
        $ciLower = round($diff - $margin, 4);
        $ciUpper = round($diff + $margin, 4);

        $regressionDetected = ($pValue < $significanceLevel) && ($diff < 0);
        $recommendation = 'PASS_PROMOTION_ALLOWED';

        if ($regressionDetected) {
            $recommendation = 'FAIL_REGRESSION_DETECTED_ROLLBACK';
        } elseif ($pValue < $significanceLevel && $diff > 0) {
            $recommendation = 'PASS_STATISTICALLY_SIGNIFICANT_IMPROVEMENT';
        }

        return [
            'hypothesis_confirmed' => ! $regressionDetected,
            'regression_detected' => $regressionDetected,
            'z_score' => round($z, 4),
            'p_value' => round($pValue, 6),
            'confidence_interval' => [
                'lower' => $ciLower,
                'upper' => $ciUpper,
            ],
            'recommendation' => $recommendation,
        ];
    }

    private function standardNormalCdf(float $x): float
    {
        // Abramowitz and Stegun approximation formula 26.2.17
        $t = 1.0 / (1.0 + 0.2316419 * abs($x));
        $d = 0.398942280401433 * exp(-$x * $x / 2.0);
        $prob = $d * $t * (0.319381530 + $t * (-0.356563782 + $t * (1.781477937 + $t * (-1.821255978 + $t * 1.330274429))));

        return $x >= 0 ? 1.0 - $prob : $prob;
    }
}
