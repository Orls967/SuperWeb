<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Pipeline;

final class StageGateEvaluator
{
    /**
     * Evaluates gate policy requirements before a progressive delivery stage transition.
     *
     * @param  array{test_pass_rate_pct: float, coverage_pct: float, migration_lint_passed: bool, p95_latency_ms: float, error_rate_pct: float}  $metrics
     * @return array{passed: bool, failed_criteria: list<string>, score: float}
     */
    public function evaluateGate(string $stage, array $metrics): array
    {
        $failedCriteria = [];
        $score = 100.0;

        $testPassRate = $metrics['test_pass_rate_pct'] ?? 0.0;
        $coverage = $metrics['coverage_pct'] ?? 0.0;
        $migrationLintPassed = (bool) ($metrics['migration_lint_passed'] ?? false);
        $p95Latency = $metrics['p95_latency_ms'] ?? 999.0;
        $errorRate = $metrics['error_rate_pct'] ?? 100.0;

        if ($testPassRate < 100.0) {
            $failedCriteria[] = "Tingkat kelulusan test ({$testPassRate}%) belum mencapai 100%.";
            $score -= 30.0;
        }

        if (! $migrationLintPassed) {
            $failedCriteria[] = 'Linter keamanan migrasi DDL menemukan pelanggaran keselamatan.';
            $score -= 40.0;
        }

        if ($stage === 'CANARY_25_PERCENT' || $stage === 'PRODUCTION_FULL_PROMOTION') {
            if ($errorRate > 0.5) {
                $failedCriteria[] = "Tingkat galat ({$errorRate}%) melampaui ambang batas toleransi (0.5%).";
                $score -= 35.0;
            }

            if ($p95Latency > 250.0) {
                $failedCriteria[] = "Latensi p95 ({$p95Latency}ms) melampaui SLA batas atas 250ms.";
                $score -= 15.0;
            }
        }

        return [
            'passed' => $failedCriteria === [],
            'failed_criteria' => $failedCriteria,
            'score' => max(0.0, $score),
        ];
    }
}
