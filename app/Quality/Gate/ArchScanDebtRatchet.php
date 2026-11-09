<?php

declare(strict_types=1);

namespace App\Quality\Gate;

/**
 * Pengelola ratchet hutang teknis arsitektur.
 */
class ArchScanDebtRatchet
{
    /**
     * @param array<string, int> $baseline
     * @param array<string, int> $current
     * @return array{can_ratchet_down: bool, updated_baseline: array<string, int>}
     */
    public function computeUpdatedBaseline(array $baseline, array $current): array
    {
        $updated = $baseline;
        $canRatchetDown = false;

        foreach ($current as $rule => $count) {
            $base = $baseline[$rule] ?? $count;
            if ($count < $base) {
                // Hutang berkurang, ratchet otomatis turun
                $updated[$rule] = $count;
                $canRatchetDown = true;
            }
        }

        return [
            'can_ratchet_down' => $canRatchetDown,
            'updated_baseline' => $updated,
        ];
    }
}

