<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Pembanding Pasangan Blok Kode (Pairwise Block Comparator).
 * Memverifikasi kesamaan token dua rentang baris kode secara detail.
 */
final class CodeDuplicationPairComparator
{
    /**
     * @param list<string> $tokensA
     * @param list<string> $tokensB
     * @return array{is_identical: bool, mismatch_indices: list<int>, similarity_percentage: float}
     */
    public function compareTokens(array $tokensA, array $tokensB): array
    {
        $lenA = count($tokensA);
        $lenB = count($tokensB);

        $minLen = min($lenA, $lenB);
        $maxLen = max($lenA, $lenB);

        if ($maxLen === 0) {
            return [
                'is_identical' => true,
                'mismatch_indices' => [],
                'similarity_percentage' => 100.0,
            ];
        }

        $mismatches = [];
        $matches = 0;

        for ($i = 0; $i < $minLen; $i++) {
            if ($tokensA[$i] === $tokensB[$i]) {
                $matches++;
            } else {
                $mismatches[] = $i;
            }
        }

        $similarity = round(($matches / (float) $maxLen) * 100.0, 2);

        return [
            'is_identical' => empty($mismatches) && $lenA === $lenB,
            'mismatch_indices' => $mismatches,
            'similarity_percentage' => $similarity,
        ];
    }
}

