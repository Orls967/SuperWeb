<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Events;

/**
 * Kalkulator Persentil Lag P95 Nearest-Rank [Fase R8]
 *
 * Persentil lag nearest-rank p95 = elemen terurut ke-ceil(0.95 * n).
 * Vektor: lag 1..20 -> 19; lag [5, 1, 9, 3] -> 9.
 */
class NearestRankLagCalculator
{
    /**
     * Hitung persentil ke-95 dari larik lag integer.
     *
     * @param  array<int, int>  $lags
     */
    public function calculateP95(array $lags): int
    {
        if (empty($lags)) {
            return 0;
        }

        $sorted = array_values($lags);
        sort($sorted, SORT_NUMERIC);

        $n = count($sorted);
        // Rank ordinal k = ceil(0.95 * n)
        // Dalam indeks array 0-based: index = k - 1
        $rank = (int) ceil(0.95 * $n);
        $rank = max(1, min($n, $rank));

        return (int) $sorted[$rank - 1];
    }
}
