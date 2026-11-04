<?php

declare(strict_types=1);

namespace Modules\Agri\Application\Services\Ndvi;

use InvalidArgumentException;

/**
 * Kalkulator NDVI rasional eksak per piksel dan per petak (D86-1).
 */
final class RationalNdviCalculator
{
    /**
     * Hitung rata-rata NDVI secara rasional eksak.
     * Mengabaikan piksel yang memiliki is_cloudy = true.
     *
     * Piksel i: NDVI = (NIR - RED) / (NIR + RED).
     * Rata-rata: sum(NDVI_i) / N_valid.
     *
     * @param  list<array{nir: int, red: int, is_cloudy?: bool}>  $pixels
     * @return array{numerator: int, denominator: int, float_val: float}
     */
    public function calculateFieldNdvi(array $pixels): array
    {
        $validPixels = array_filter($pixels, static fn (array $p): bool => empty($p['is_cloudy']));

        if (empty($validPixels)) {
            throw new InvalidArgumentException('Tidak ada piksel valid tanpa tutupan awan');
        }

        $sumNumerator = 0;
        $sumDenominator = 1;

        foreach ($validPixels as $pixel) {
            $nir = $pixel['nir'];
            $red = $pixel['red'];
            $denom = $nir + $red;

            if ($denom === 0) {
                continue;
            }

            $num = $nir - $red;

            // Tambahkan pecahan: sumNum/sumDenom + num/denom
            $newNum = ($sumNumerator * $denom) + ($num * $sumDenominator);
            $newDenom = $sumDenominator * $denom;

            $gcd = $this->gcd(abs($newNum), abs($newDenom));
            $sumNumerator = $newNum / $gcd;
            $sumDenominator = $newDenom / $gcd;
        }

        $nValid = count($validPixels);
        $finalNum = $sumNumerator;
        $finalDenom = $sumDenominator * $nValid;

        $gcdFinal = $this->gcd(abs((int) $finalNum), abs((int) $finalDenom));
        $simplifiedNum = (int) ($finalNum / $gcdFinal);
        $simplifiedDenom = (int) ($finalDenom / $gcdFinal);

        if ($simplifiedDenom < 0) {
            $simplifiedNum = -$simplifiedNum;
            $simplifiedDenom = -$simplifiedDenom;
        }

        return [
            'numerator' => $simplifiedNum,
            'denominator' => $simplifiedDenom,
            'float_val' => $simplifiedDenom !== 0 ? round($simplifiedNum / $simplifiedDenom, 6) : 0.0,
        ];
    }

    /**
     * Bandingkan pecahan [num, denom] dengan ambang batas (bps, di mana 10.000 bps = 1.0).
     *
     * @param  int  $thresholdBps  e.g. 4500 bps = 0.45
     * @return bool True jika num/denom >= thresholdBps / 10000
     */
    public function meetsThreshold(int $num, int $denom, int $thresholdBps): bool
    {
        if ($denom <= 0) {
            return false;
        }

        // num / denom >= thresholdBps / 10000 <=> num * 10000 >= thresholdBps * denom
        return ($num * 10000) >= ($thresholdBps * $denom);
    }

    private function gcd(int $a, int $b): int
    {
        if ($b === 0) {
            return $a === 0 ? 1 : $a;
        }

        return $this->gcd($b, $a % $b);
    }
}
