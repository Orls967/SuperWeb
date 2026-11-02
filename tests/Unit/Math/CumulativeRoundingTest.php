<?php

declare(strict_types=1);

namespace Tests\Unit\Math;

use Brick\Math\BigRational;
use InvalidArgumentException;
use Modules\Shared\Domain\Math\CumulativeRounding;
use Modules\Shared\Domain\Math\CumulativeRoundingCertificate;
use Modules\Shared\Domain\Math\CumulativeRoundingCertificateVerifier;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(CumulativeRounding::class, CumulativeRoundingCertificateVerifier::class);

it('rejects invalid inputs', function (): void {
    expect(fn () => CumulativeRounding::schedule(-1, 10))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CumulativeRounding::schedule(100, 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CumulativeRounding::schedule(100, -5))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CumulativeRounding::scheduleWeighted(-10, [1, 2]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CumulativeRounding::scheduleWeighted(100, []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CumulativeRounding::scheduleWeighted(100, [10, 0]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CumulativeRounding::scheduleWeighted(100, [10, -2]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CumulativeRounding::earnedTo(-1, 5, 10))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CumulativeRounding::earnedTo(100, 5, 0))->toThrow(InvalidArgumentException::class);
});

it('handles zero total in schedule and weighted schedule', function (): void {
    $schedule = CumulativeRounding::schedule(0, 5);
    expect($schedule)->toBe([0, 0, 0, 0, 0])
        ->and(array_sum($schedule))->toBe(0);

    $weighted = CumulativeRounding::scheduleWeighted(0, [2, 3]);
    expect($weighted)->toBe([0, 0])
        ->and(array_sum($weighted))->toBe(0);

    // 1 period exact boundary
    $single = CumulativeRounding::schedule(500, 1);
    expect($single)->toBe([500]);
});

it('tests verifier negative step and missing index validation', function (): void {
    $exact = [BigRational::of('50'), BigRational::of('50')];
    $certNegative = new CumulativeRoundingCertificate(100, 2, [-10, 110], $exact);
    expect(fn () => CumulativeRoundingCertificateVerifier::verify($certNegative))
        ->toThrow(InvalidCertificateException::class);

    $certMissing = new CumulativeRoundingCertificate(100, 2, [50, 50], [BigRational::of('50')]);
    expect(fn () => CumulativeRoundingCertificateVerifier::verify($certMissing))
        ->toThrow(InvalidCertificateException::class);
});

it('matches required vector: 100.000 dalam 30 hari', function (): void {
    $schedule = CumulativeRounding::schedule(100_000, 30);

    expect(count($schedule))->toBe(30)
        ->and($schedule[0])->toBe(3_333)
        ->and($schedule[1])->toBe(3_333)
        ->and($schedule[2])->toBe(3_334)
        ->and(array_sum($schedule))->toBe(100_000)
        ->and(CumulativeRounding::earnedTo(100_000, 30, 30))->toBe(100_000);
});

it('handles earnedTo boundary cases', function (): void {
    expect(CumulativeRounding::earnedTo(100_000, 0, 30))->toBe(0)
        ->and(CumulativeRounding::earnedTo(100_000, -5, 30))->toBe(0)
        ->and(CumulativeRounding::earnedTo(100_000, 15, 30))->toBe(50_000)
        ->and(CumulativeRounding::earnedTo(100_000, 35, 30))->toBe(100_000);
});

it('supports weighted cumulative rounding', function (): void {
    $schedule = CumulativeRounding::scheduleWeighted(100, [1, 1, 2]);

    expect(count($schedule))->toBe(3)
        ->and($schedule[0])->toBe(25)
        ->and($schedule[1])->toBe(25)
        ->and($schedule[2])->toBe(50)
        ->and(array_sum($schedule))->toBe(100);
});

it('enforces certificate verification and rejects tampered certificates', function (): void {
    $schedule = CumulativeRounding::schedule(10_000, 10);
    $exact = array_fill(0, 10, BigRational::of('1000'));

    $validCert = new CumulativeRoundingCertificate(10_000, 10, $schedule, $exact);
    CumulativeRoundingCertificateVerifier::verify($validCert);

    // Tamper with sum
    $tamperedSum = new CumulativeRoundingCertificate(10_000, 10, [1001, ...array_slice($schedule, 1)], $exact);
    expect(fn () => CumulativeRoundingCertificateVerifier::verify($tamperedSum))->toThrow(InvalidCertificateException::class);

    // Tamper with variance (> 1 away)
    $tamperedVariance = new CumulativeRoundingCertificate(10_000, 10, [998, 1002, ...array_slice($schedule, 2)], $exact);
    expect(fn () => CumulativeRoundingCertificateVerifier::verify($tamperedVariance))->toThrow(InvalidCertificateException::class);
});

it('satisfies properties across 500 random cases (default suite)', function (): void {
    $seed = 20261031_02;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 500; $i++) {
        $total = $randomizer->getInt(0, 1_000_000);
        $periods = $randomizer->getInt(1, 365);

        $schedule = CumulativeRounding::schedule($total, $periods);

        // Sum invariant
        expect(array_sum($schedule))->toBe($total);

        // Step bounds
        $floor = intdiv($total, $periods);
        $ceil = ($total % $periods === 0) ? $floor : $floor + 1;
        foreach ($schedule as $step) {
            expect($step === $floor || $step === $ceil)->toBeTrue();
        }

        // earnedTo monotonicity
        $prev = 0;
        for ($k = 0; $k <= $periods; $k++) {
            $curr = CumulativeRounding::earnedTo($total, $k, $periods);
            expect($curr)->toBeGreaterThanOrEqual($prev);
            $prev = $curr;
        }
    }
});

it('satisfies scale properties across 10.000 random cases', function (): void {
    $seed = 20261031_03;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 10_000; $i++) {
        $total = $randomizer->getInt(0, 500_000);
        $periods = $randomizer->getInt(1, 100);

        $schedule = CumulativeRounding::schedule($total, $periods);
        expect(array_sum($schedule))->toBe($total);
    }
})->group('skala');

it('benchmarks cumulative rounding scalability', function (): void {
    $start = microtime(true);
    $schedule = CumulativeRounding::schedule(1_000_000, 10_000);
    $duration = microtime(true) - $start;

    expect(count($schedule))->toBe(10_000)
        ->and(array_sum($schedule))->toBe(1_000_000)
        ->and($duration)->toBeLessThan(0.15); // Under 150ms
})->group('skala');
