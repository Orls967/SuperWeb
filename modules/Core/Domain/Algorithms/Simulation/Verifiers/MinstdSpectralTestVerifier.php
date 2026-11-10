<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation\Verifiers;

/**
 * Verifier integritas parameter generator Lehmer LCG MINSTD Park-Miller.
 * Formula: X_{n+1} = (a * X_n) mod m.
 * Konstanta baku: a = 48271, m = 2^31 - 1 = 2147483647 (bilangan prima Mersenne M31).
 */
final class MinstdSpectralTestVerifier
{
    public const MULTIPLIER = 48271;

    public const MODULUS = 2147483647; // 2^31 - 1

    /**
     * Memverifikasi bahwa seed berada dalam grup perkalian modulo m (Z_m^*).
     */
    public function isValidSeed(int $seed): bool
    {
        return $seed >= 1 && $seed < self::MODULUS;
    }

    /**
     * Memverifikasi keselarasan satu langkah transisi MINSTD.
     */
    public function verifyStep(int $currentSeed, int $nextSeed): bool
    {
        if (! $this->isValidSeed($currentSeed)) {
            return false;
        }

        $expectedNext = ($currentSeed * self::MULTIPLIER) % self::MODULUS;

        return $nextSeed === $expectedNext;
    }

    /**
     * Memverifikasi keseragaman statistik dasar sampel MINSTD (uji Chi-Square sederhana).
     *
     * @param  array<int, int>  $samples
     * @return array{chi_square: float, degree_of_freedom: int, uniform_enough: bool}
     */
    public function verifyDistribution(array $samples, int $bucketCount = 10): array
    {
        $n = count($samples);
        if ($n === 0) {
            return ['chi_square' => 0.0, 'degree_of_freedom' => 0, 'uniform_enough' => false];
        }

        $expectedPerBucket = $n / $bucketCount;
        $buckets = array_fill(0, $bucketCount, 0);

        foreach ($samples as $val) {
            $normalized = $val / self::MODULUS;
            $idx = (int) floor($normalized * $bucketCount);
            if ($idx >= $bucketCount) {
                $idx = $bucketCount - 1;
            }
            $buckets[$idx]++;
        }

        $chiSquare = 0.0;
        foreach ($buckets as $obs) {
            $diff = $obs - $expectedPerBucket;
            $chiSquare += ($diff * $diff) / $expectedPerBucket;
        }

        $dof = $bucketCount - 1;
        // Nilai kritis kasar untuk dof=9 pada alpha=0.01 adalah ~21.66
        $uniformEnough = $chiSquare < 25.0;

        return [
            'chi_square' => round($chiSquare, 4),
            'degree_of_freedom' => $dof,
            'uniform_enough' => $uniformEnough,
        ];
    }
}
