<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

/**
 * Service pengawas ratchet baseline arsitektur dan hutang teknis kualitas kode.
 */
class QualityRatchetDebtService
{
    /**
     * Membandingkan hasil scan terhadap baseline ratchet
     *
     * @param  array<string, int>  $baseline
     * @param  array<string, int>  $current
     * @return array{debt_change: int, regressions: list<string>, improvements: list<string>}
     */
    public function evaluateRatchet(array $baseline, array $current): array
    {
        $regressions = [];
        $improvements = [];
        $netChange = 0;

        foreach ($current as $rule => $count) {
            $baseCount = $baseline[$rule] ?? 0;
            $diff = $count - $baseCount;

            if ($diff > 0) {
                $regressions[] = sprintf('Regresi pada aturan %s: bertambah %d pelanggaran (%d -> %d)', $rule, $diff, $baseCount, $count);
                $netChange += $diff;
            } elseif ($diff < 0) {
                $improvements[] = sprintf('Perbaikan pada aturan %s: berkurang %d pelanggaran (%d -> %d)', $rule, abs($diff), $baseCount, $count);
                $netChange += $diff;
            }
        }

        return [
            'debt_change' => $netChange,
            'regressions' => $regressions,
            'improvements' => $improvements,
        ];
    }
}
