<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Algorithms;

use Modules\Shared\Domain\Algorithms\Aviation\FareBucketYieldOptimizer as SharedOptimizer;

/**
 * Algoritma A6: Fare Bucket Yield Optimizer.
 *
 * Mengoptimalkan tingkat proteksi kursi (protection levels) dan batas pemesanan
 * (booking limits) untuk kelas tarif bersarang (nested fare classes) menggunakan
 * metode Expected Marginal Seat Revenue versi b (EMSR-b) diskret dan Teorema Littlewood.
 */
class FareBucketYieldOptimizer
{
    private readonly SharedOptimizer $engine;

    public function __construct(?SharedOptimizer $engine = null)
    {
        $this->engine = $engine ?? new SharedOptimizer();
    }

    /**
     * Hitung tingkat proteksi kursi dan batas pemesanan bersarang.
     *
     * @param  int  $totalCapacity  Kapasitas kursi pesawat yang tersedia
     * @param  array<int, array{code: string, fare: int, mean_demand: float|int, std_dev: float|int}>  $classes
     * @return array{
     *     total_capacity: int,
     *     buckets: array<int, array{code: string, fare: int, protection_level: int, booking_limit: int}>,
     *     certificate_hash: string,
     *     algorithm_version: string
     * }
     */
    public function calculateProtectionAndBookingLimits(int $totalCapacity, array $classes): array
    {
        $result = $this->engine->optimize($totalCapacity, $classes);

        return [
            'total_capacity' => $result->totalCapacity,
            'buckets' => $result->buckets,
            'certificate_hash' => $result->certificate->hash,
            'algorithm_version' => $result->certificate->algorithmVersion,
        ];
    }

    /**
     * Aproksimasi Beasley-Springer-Moro untuk inversi CDF distribusi normal standar Phi^-1(p).
     */
    public function inverseStandardNormalCdf(float $p): float
    {
        return $this->engine->inverseStandardNormalCdf($p);
    }
}
