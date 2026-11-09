<?php

declare(strict_types=1);

namespace App\Quality\Gate;

use Modules\Platform\Domain\Models\PhaseMetricSnapshot;

/**
 * Evaluator Gerbang Kualitas Fase Resmi Gelombang 8.
 */
class PhaseGateEvaluator
{
    public const MIN_B1_LOC = 7500;

    public const MIN_B3_DOC = 1500;

    public const MIN_DECISION_DENSITY = 0.0500;

    public const MAX_DUPLICATE_RATIO = 0.0100;

    /**
     * @return array{passed: bool, checks: array<string, array{actual: mixed, required: mixed, passed: bool}>}
     */
    public function evaluateGate(PhaseMetricSnapshot $snapshot, int $actualDocLines): array
    {
        $checks = [
            'B1_LOC' => [
                'actual' => $snapshot->b1_loc,
                'required' => self::MIN_B1_LOC,
                'passed' => $snapshot->b1_loc >= self::MIN_B1_LOC,
            ],
            'B3_DOC' => [
                'actual' => $actualDocLines,
                'required' => self::MIN_B3_DOC,
                'passed' => $actualDocLines >= self::MIN_B3_DOC,
            ],
            'DECISION_DENSITY' => [
                'actual' => $snapshot->decision_density,
                'required' => self::MIN_DECISION_DENSITY,
                'passed' => $snapshot->decision_density >= self::MIN_DECISION_DENSITY,
            ],
            'DUPLICATE_RATIO' => [
                'actual' => $snapshot->duplicate_ratio,
                'required' => self::MAX_DUPLICATE_RATIO,
                'passed' => $snapshot->duplicate_ratio <= self::MAX_DUPLICATE_RATIO,
            ],
            'ORPHAN_CLASSES' => [
                'actual' => $snapshot->orphan_count,
                'required' => 0,
                'passed' => $snapshot->orphan_count === 0,
            ],
        ];

        $allPassed = true;
        foreach ($checks as $c) {
            if (! $c['passed']) {
                $allPassed = false;
                break;
            }
        }

        return [
            'passed' => $allPassed,
            'checks' => $checks,
        ];
    }
}
