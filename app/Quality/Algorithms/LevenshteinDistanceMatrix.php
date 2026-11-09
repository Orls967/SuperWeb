<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Pembangun Matriks Jarak Levenshtein untuk perbandingan token edit-distance.
 */
final class LevenshteinDistanceMatrix
{
    /**
     * Menghitung jarak Levenshtein antara dua barisan token string
     *
     * @param  list<string>  $tokensA
     * @param  list<string>  $tokensB
     */
    public function computeTokenDistance(array $tokensA, array $tokensB): int
    {
        $m = count($tokensA);
        $n = count($tokensB);

        $dp = [];
        for ($i = 0; $i <= $m; $i++) {
            $dp[$i][0] = $i;
        }
        for ($j = 0; $j <= $n; $j++) {
            $dp[0][$j] = $j;
        }

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $cost = ($tokensA[$i - 1] === $tokensB[$j - 1]) ? 0 : 1;
                $dp[$i][$j] = min(
                    $dp[$i - 1][$j] + 1,       // Deletion
                    $dp[$i][$j - 1] + 1,       // Insertion
                    $dp[$i - 1][$j - 1] + $cost // Substitution
                );
            }
        }

        return $dp[$m][$n];
    }

    /**
     * Rasio kesamaan Levenshtein bernilai [0.0, 1.0]
     *
     * @param  list<string>  $tokensA
     * @param  list<string>  $tokensB
     */
    public function computeSimilarity(array $tokensA, array $tokensB): float
    {
        $maxLen = max(count($tokensA), count($tokensB));
        if ($maxLen === 0) {
            return 1.0;
        }

        $dist = $this->computeTokenDistance($tokensA, $tokensB);

        return round(1.0 - ($dist / $maxLen), 4);
    }
}
