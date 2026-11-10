<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

/**
 * Detektor drift tingkat rendah untuk menganalisis divergensi bitwise rantai hash SHA-256.
 */
final class CryptographicDriftDetectorService
{
    /**
     * @return array{
     *     identical: bool,
     *     hamming_distance: int,
     *     first_different_nibble_idx: int|null,
     *     similarity_pct: float
     * }
     */
    public function analyzeHashDifference(string $hashA, string $hashB): array
    {
        $a = strtolower(trim($hashA));
        $b = strtolower(trim($hashB));

        if ($a === $b) {
            return [
                'identical' => true,
                'hamming_distance' => 0,
                'first_different_nibble_idx' => null,
                'similarity_pct' => 100.0,
            ];
        }

        $len = min(strlen($a), strlen($b));
        $hammingDist = 0;
        $firstDiff = null;

        for ($i = 0; $i < $len; $i++) {
            $valA = hexdec($a[$i]);
            $valB = hexdec($b[$i]);
            $xor = $valA ^ $valB;

            if ($xor !== 0) {
                if ($firstDiff === null) {
                    $firstDiff = $i;
                }
                // Hitung bit 1 pada XOR
                $hammingDist += substr_count(decbin($xor), '1');
            }
        }

        $totalBits = $len * 4;
        $similarity = round((($totalBits - $hammingDist) / $totalBits) * 100, 2);

        return [
            'identical' => false,
            'hamming_distance' => $hammingDist,
            'first_different_nibble_idx' => $firstDiff,
            'similarity_pct' => $similarity,
        ];
    }
}
