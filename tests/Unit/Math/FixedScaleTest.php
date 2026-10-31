<?php

declare(strict_types=1);

namespace Tests\Unit\Math;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use DivisionByZeroError;
use InvalidArgumentException;
use Modules\Shared\Domain\Math\FixedScale;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(FixedScale::class);

it('rejects invalid inputs', function (): void {
    expect(fn () => FixedScale::mulDivHalfUp(100))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FixedScale::mulDivHalfUp())->toThrow(InvalidArgumentException::class)
        ->and(fn () => FixedScale::mulDivHalfUp(100, 200, 0))->toThrow(DivisionByZeroError::class);
});

it('matches required vector: 123.456 x 12.345.678 / 10^8 -> 15.241', function (): void {
    $result = FixedScale::mulDivHalfUp(123_456, 12_345_678, 100_000_000);

    expect($result)->toBe(15_241);
});

it('correctly rounds HalfUp on exact .5 and .4 boundaries', function (): void {
    // 15 / 10 = 1.5 -> 2
    expect(FixedScale::mulDivHalfUp(15, 10))->toBe(2)
        // 14 / 10 = 1.4 -> 1
        ->and(FixedScale::mulDivHalfUp(14, 10))->toBe(1)
        // 25 / 10 = 2.5 -> 3
        ->and(FixedScale::mulDivHalfUp(25, 10))->toBe(3)
        // 3 factors: 2 * 3 * 5 / 4 = 30 / 4 = 7.5 -> 8
        ->and(FixedScale::mulDivHalfUp(2, 3, 5, 4))->toBe(8);
});

it('satisfies equivalence property with BigRational over 500 cases', function (): void {
    $seed = 20261031_04;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 500; $i++) {
        $a = $randomizer->getInt(-1_000_000, 1_000_000);
        $b = $randomizer->getInt(-1_000_000, 1_000_000);
        $d = $randomizer->getInt(1, 100_000);

        $actual = FixedScale::mulDivHalfUp($a, $b, $d);

        $productRational = BigRational::of((string) $a)->multipliedBy(BigRational::of((string) $b));
        $rational = $productRational->dividedBy(BigRational::of((string) $d));
        $expected = $rational->toScale(0, RoundingMode::HalfUp)->toBigInteger()->toInt();

        expect($actual)->toBe($expected);
    }
});

it('satisfies scale equivalence across 10.000 random cases', function (): void {
    $seed = 20261031_05;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 10_000; $i++) {
        $a = $randomizer->getInt(0, 500_000);
        $b = $randomizer->getInt(0, 500_000);
        $d = $randomizer->getInt(1, 10_000);

        $actual = FixedScale::mulDivHalfUp($a, $b, $d);
        $expected = BigRational::of((string) $a)
            ->multipliedBy(BigRational::of((string) $b))
            ->dividedBy(BigRational::of((string) $d))
            ->toScale(0, RoundingMode::HalfUp)
            ->toBigInteger()
            ->toInt();

        expect($actual)->toBe($expected);
    }
})->group('skala');

it('benchmarks FixedScale mulDivHalfUp scalability', function (): void {
    $start = microtime(true);
    for ($i = 0; $i < 50_000; $i++) {
        FixedScale::mulDivHalfUp(123_456, 12_345_678, 100_000_000);
    }
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(0.30);
})->group('skala');
