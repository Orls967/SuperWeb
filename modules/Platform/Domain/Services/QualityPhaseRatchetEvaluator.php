<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\PhaseMetricSnapshot;

/**
 * Service pengevaluasi kemajuan kualitas dan kepatuhan ratchet metrik per fase gelombang.
 */
class QualityPhaseRatchetEvaluator
{
    /**
     * Memeriksa apakah fase saat ini memenuhi standar minimum Gelombang 8
     *
     * @return array{is_compliant: bool, checks: array<string, bool>, failures: list<string>}
     */
    public function evaluatePhaseCompliance(PhaseMetricSnapshot $snapshot, int $minB1Target = 7500): array
    {
        $failures = [];
        $checks = [
            'b1_loc_threshold' => $snapshot->b1_loc >= $minB1Target,
            'decision_density' => $snapshot->decision_density >= 0.05,
            'duplicate_ratio' => $snapshot->duplicate_ratio <= 0.01,
            'zero_orphans' => $snapshot->orphan_count === 0,
        ];

        if (! $checks['b1_loc_threshold']) {
            $failures[] = sprintf('Volume B1 LOC (%d) masih di bawah target keras fase (%d)', $snapshot->b1_loc, $minB1Target);
        }

        if (! $checks['decision_density']) {
            $failures[] = sprintf('Decision density (%.4f) di bawah ambang minimum 0.0500', $snapshot->decision_density);
        }

        if (! $checks['duplicate_ratio']) {
            $failures[] = sprintf('Rasio duplikasi kode (%.2f%%) melampaui toleransi 1.00%%', $snapshot->duplicate_ratio * 100);
        }

        if (! $checks['zero_orphans']) {
            $failures[] = sprintf('Ditemukan %d kelas yatim piatu tak terjangkau di codebase', $snapshot->orphan_count);
        }

        return [
            'is_compliant' => empty($failures),
            'checks' => $checks,
            'failures' => $failures,
        ];
    }
}

