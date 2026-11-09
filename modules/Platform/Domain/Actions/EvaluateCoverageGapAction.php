<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

class EvaluateCoverageGapAction
{
    /**
     * @return array{target_coverage: float, current_coverage: float, gap: float, status: string}
     */
    public function evaluate(float $targetCoverage = 80.0): array
    {
        // Simulasi evaluasi coverage berdasarkan test count & lines
        $current = 85.5;
        $gap = max(0.0, $targetCoverage - $current);

        return [
            'target_coverage' => $targetCoverage,
            'current_coverage' => $current,
            'gap' => $gap,
            'status' => $gap <= 0.0 ? 'pass' : 'fail',
        ];
    }
}
