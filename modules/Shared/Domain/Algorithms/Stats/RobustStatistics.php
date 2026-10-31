<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Stats;

use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use InvalidArgumentException;

/**
 * Statistik robust (Median dan Median Absolute Deviation - MAD) tanpa angka pecahan mengambang (float).
 * Digunakan untuk deteksi anomali telemetri dan konsumsi bahan bakar (O-2, T-8).
 *
 * Kompleksitas: O(N log N) karena pengurutan nilai dan pengurutan deviasi absolut.
 */
final class RobustStatistics
{
    /**
     * Menghitung Median dari kumpulan nilai integer.
     * Bila jumlah elemen genap, median adalah rata-rata dua nilai tengah sebagai BigRational eksak.
     *
     * @param  list<int>  $values
     */
    public function median(array $values): BigRational
    {
        if ($values === []) {
            throw new InvalidArgumentException('Kumpulan nilai untuk median tidak boleh kosong');
        }

        $sorted = $values;
        sort($sorted, SORT_NUMERIC);
        $count = count($sorted);

        $mid = intdiv($count, 2);

        if ($count % 2 === 1) {
            return BigRational::of($sorted[$mid]);
        }

        // Rata-rata dua nilai tengah: (a + b) / 2
        return BigRational::of($sorted[$mid - 1] + $sorted[$mid])->dividedBy(BigRational::of(2));
    }

    /**
     * Menghitung Median Absolute Deviation (MAD) dari kumpulan nilai integer terhadap median yang diberikan.
     * MAD = median(|x_i - median|).
     *
     * @param  list<int>  $values
     */
    public function mad(array $values, BigRational $median): BigRational
    {
        if ($values === []) {
            throw new InvalidArgumentException('Kumpulan nilai untuk MAD tidak boleh kosong');
        }

        $num = $median->getNumerator()->toInt();
        $den = $median->getDenominator()->toInt();

        // |x_i - num/den| = |den * x_i - num| / den
        // Mengurutkan integer |den * x_i - num| jauh lebih cepat (native C sort) dan eksak
        $scaledDeviations = [];
        foreach ($values as $val) {
            $scaledDeviations[] = abs($den * $val - $num);
        }

        sort($scaledDeviations, SORT_NUMERIC);

        $count = count($scaledDeviations);
        $mid = intdiv($count, 2);

        if ($count % 2 === 1) {
            $intMedian = BigRational::of($scaledDeviations[$mid]);
        } else {
            $intMedian = BigRational::of($scaledDeviations[$mid - 1] + $scaledDeviations[$mid])
                ->dividedBy(BigRational::of(2));
        }

        return $intMedian->dividedBy(BigRational::of($den));
    }

    /**
     * Memeriksa apakah nilai $x merupakan anomali berdasarkan rumus O-2:
     * |x − median| × 10_000 × 10_000 > k_bps × 14_826 × MAD
     * Bila MAD == 0, menggunakan ambang batas absolut minimum (absoluteFloor).
     *
     * @param  int  $x  Nilai sampel yang diuji
     * @param  BigRational  $median  Median baseline
     * @param  BigRational  $mad  MAD baseline
     * @param  int  $kBps  Ambang sensitivitas dalam basis poin (misal 35_000 = 3,5)
     * @param  int  $absoluteFloor  Ambang batas deviasi absolut minimum bila MAD == 0
     */
    public function isAnomaly(
        int $x,
        BigRational $median,
        BigRational $mad,
        int $kBps,
        int $absoluteFloor
    ): bool {
        if ($kBps <= 0) {
            throw new InvalidArgumentException("kBps harus positif: {$kBps}");
        }

        if ($absoluteFloor < 0) {
            throw new InvalidArgumentException("absoluteFloor tidak boleh negatif: {$absoluteFloor}");
        }

        $deviation = BigRational::of($x)->minus($median)->abs();

        if ($mad->isZero()) {
            return $deviation->isGreaterThan(BigRational::of($absoluteFloor));
        }

        // Rumus integer / BigRational:
        // LHS = |x - median| * 100_000_000
        // RHS = (kBps * 14_826) * MAD
        $lhs = $deviation->multipliedBy(BigRational::of(100_000_000));
        $rhsFactor = BigInteger::of($kBps)->multipliedBy(BigInteger::of(14_826));
        $rhs = $mad->multipliedBy(BigRational::of($rhsFactor));

        return $lhs->isGreaterThan($rhs);
    }
}
