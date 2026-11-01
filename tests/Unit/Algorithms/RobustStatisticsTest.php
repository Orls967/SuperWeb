<?php

declare(strict_types=1);

use Brick\Math\BigRational;
use Modules\Shared\Domain\Algorithms\Stats\RobustStatistics;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(RobustStatistics::class);

describe('RobustStatistics', function (): void {
    it('validates empty inputs and negative parameters', function (): void {
        $stats = new RobustStatistics;

        expect(fn () => $stats->median([]))->toThrow(InvalidArgumentException::class);
        expect(fn () => $stats->mad([], BigRational::zero()))->toThrow(InvalidArgumentException::class);
        expect(fn () => $stats->isAnomaly(10, BigRational::zero(), BigRational::one(), 0, 5))
            ->toThrow(InvalidArgumentException::class);
        expect(fn () => $stats->isAnomaly(10, BigRational::zero(), BigRational::one(), 35000, -1))
            ->toThrow(InvalidArgumentException::class);
    });

    it('matches the mandatory test vectors exactly', function (): void {
        $stats = new RobustStatistics;

        $values = [10, 12, 11, 13, 100];
        $median = $stats->median($values);
        $mad = $stats->mad($values, $median);

        expect($median->isEqualTo(BigRational::of(12)))->toBeTrue()
            ->and($mad->isEqualTo(BigRational::of(1)))->toBeTrue();

        $k = 35000;
        // 13 is not anomaly
        expect($stats->isAnomaly(13, $median, $mad, $k, 5))->toBeFalse();

        // 100 is anomaly
        expect($stats->isAnomaly(100, $median, $mad, $k, 5))->toBeTrue();

        // 10 is not anomaly (|10 - 12| * 1e8 = 2e8 <= 5.189e8)
        expect($stats->isAnomaly(10, $median, $mad, $k, 5))->toBeFalse();
    });

    it('uses absoluteFloor when MAD is zero', function (): void {
        $stats = new RobustStatistics;

        // All values identical -> MAD is 0
        $values = [50, 50, 50, 50];
        $median = $stats->median($values);
        $mad = $stats->mad($values, $median);

        expect($median->isEqualTo(BigRational::of(50)))->toBeTrue()
            ->and($mad->isZero())->toBeTrue();

        // With absoluteFloor = 10:
        // x = 60 -> deviation 10 -> not anomaly
        expect($stats->isAnomaly(60, $median, $mad, 35000, 10))->toBeFalse();

        // x = 61 -> deviation 11 > 10 -> anomaly
        expect($stats->isAnomaly(61, $median, $mad, 35000, 10))->toBeTrue();

        // x = 39 -> deviation 11 > 10 -> anomaly
        expect($stats->isAnomaly(39, $median, $mad, 35000, 10))->toBeTrue();
    });

    it('calculates exact fractional median for even element arrays', function (): void {
        $stats = new RobustStatistics;

        $evenValues = [10, 25];
        $med = $stats->median($evenValues);
        expect($med->isEqualTo(BigRational::of('35/2')))->toBeTrue();

        $mad = $stats->mad($evenValues, $med);
        expect($mad->isEqualTo(BigRational::of('15/2')))->toBeTrue();

        // Test odd count for mad with non-integer median
        $threeValues = [10, 20, 40];
        $med3 = $stats->median($threeValues);
        $mad3 = $stats->mad($threeValues, $med3);
        expect($med3->isEqualTo(BigRational::of(20)))->toBeTrue()
            ->and($mad3->isEqualTo(BigRational::of(10)))->toBeTrue();

        // Test even count for mad with integer median
        $fourValues = [10, 12, 14, 16];
        $med4 = $stats->median($fourValues); // 13
        $mad4 = $stats->mad($fourValues, $med4); // deviations: [1, 1, 3, 3] -> median (1+3)/2 = 2
        expect($med4->isEqualTo(BigRational::of(13)))->toBeTrue()
            ->and($mad4->isEqualTo(BigRational::of(2)))->toBeTrue();
    });

    it('kills boundary mutations on kBps and anomaly calculation', function (): void {
        $stats = new RobustStatistics;

        // kBps = 1 is allowed and works
        expect($stats->isAnomaly(100, BigRational::zero(), BigRational::one(), 1, 0))->toBeTrue();

        // Exact boundary condition test:
        // When deviation = 14826, MAD = 10000, kBps = 10000:
        // LHS = 14826 * 100_000_000 = 1_482_600_000_000
        // RHS = (10000 * 14826) * 10000 = 1_482_600_000_000
        // LHS is not greater than RHS -> false
        expect($stats->isAnomaly(14826, BigRational::zero(), BigRational::of(10000), 10000, 0))->toBeFalse();

        // If deviation is 14827 -> LHS = 14827 * 1e8 > RHS -> true
        expect($stats->isAnomaly(14827, BigRational::zero(), BigRational::of(10000), 10000, 0))->toBeTrue();
    });

    it('property test: permutation invariance and bounds', function (): void {
        $stats = new RobustStatistics;
        $seed = 44556677;
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

        for ($trial = 0; $trial < 500; $trial++) {
            $n = $randomizer->getInt(3, 20);
            $values = [];
            for ($i = 0; $i < $n; $i++) {
                $values[] = $randomizer->getInt(-5000, 5000);
            }

            $med = $stats->median($values);
            $mad = $stats->mad($values, $med);

            // Invariant 1: min <= median <= max
            $min = min($values);
            $max = max($values);
            expect($med->isGreaterThanOrEqualTo(BigRational::of($min)))->toBeTrue()
                ->and($med->isLessThanOrEqualTo(BigRational::of($max)))->toBeTrue();

            // Invariant 2: MAD >= 0
            expect($mad->isGreaterThanOrEqualTo(BigRational::zero()))->toBeTrue();

            // Invariant 3: Permutation invariance (shuffling preserves median and MAD)
            $shuffled = $values;
            $shuffled = $randomizer->shuffleArray($shuffled);
            $shuffledMed = $stats->median($shuffled);
            $shuffledMad = $stats->mad($shuffled, $shuffledMed);

            expect($med->isEqualTo($shuffledMed))->toBeTrue()
                ->and($mad->isEqualTo($shuffledMad))->toBeTrue();
        }
    });

    it('benchmarks 20000 elements median and MAD < 0.5s', function (): void {
        $stats = new RobustStatistics;
        $values = [];
        for ($i = 0; $i < 20000; $i++) {
            $values[] = ($i * 7 + 13) % 1000;
        }

        $start = microtime(true);
        $med = $stats->median($values);
        $mad = $stats->mad($values, $med);
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeLessThan(0.5);
    })->group('skala');
});
