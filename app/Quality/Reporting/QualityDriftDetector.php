<?php

declare(strict_types=1);

namespace App\Quality\Reporting;

/**
 * Pendeteksi pergeseran metrik (Drift Detector) antar dua snapshot fase.
 */
final class QualityDriftDetector
{
    /**
     * @param array<string, float|int> $baseSnapshot
     * @param array<string, float|int> $currentSnapshot
     * @return array{has_drift: bool, delta_loc: int, delta_density: float, delta_dup_ratio: float, summary: string}
     */
    public function detectDrift(array $baseSnapshot, array $currentSnapshot): array
    {
        $baseLoc = (int) ($baseSnapshot['b1_loc'] ?? 0);
        $currLoc = (int) ($currentSnapshot['b1_loc'] ?? 0);
        $deltaLoc = $currLoc - $baseLoc;

        $baseDensity = (float) ($baseSnapshot['decision_density'] ?? 0.0);
        $currDensity = (float) ($currentSnapshot['decision_density'] ?? 0.0);
        $deltaDensity = round($currDensity - $baseDensity, 4);

        $baseDup = (float) ($baseSnapshot['duplicate_ratio'] ?? 0.0);
        $currDup = (float) ($currentSnapshot['duplicate_ratio'] ?? 0.0);
        $deltaDup = round($currDup - $baseDup, 4);

        $hasDrift = abs($deltaLoc) > 100 || abs($deltaDensity) > 0.02 || abs($deltaDup) > 0.005;

        $summary = sprintf(
            'Drift delta: LOC %+d, Density %+.4f, Duplication %+.2f%% (%s)',
            $deltaLoc,
            $deltaDensity,
            $deltaDup * 100,
            $hasDrift ? 'TERDETEKSI PERGESERAN' : 'STABIL'
        );

        return [
            'has_drift' => $hasDrift,
            'delta_loc' => $deltaLoc,
            'delta_density' => $deltaDensity,
            'delta_dup_ratio' => $deltaDup,
            'summary' => $summary,
        ];
    }
}

