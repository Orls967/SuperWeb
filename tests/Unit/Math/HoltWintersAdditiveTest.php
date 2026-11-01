<?php

declare(strict_types=1);

namespace Tests\Unit\Math;

use InvalidArgumentException;
use Modules\Shared\Domain\Math\HoltWintersAdditive;
use Modules\Shared\Domain\Math\Mape;
use Modules\Shared\Domain\Math\SeasonalNaive;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(HoltWintersAdditive::class, SeasonalNaive::class, Mape::class);

it('rejects invalid inputs across forecasting suite', function (): void {
    expect(fn () => HoltWintersAdditive::forecast([10], horizon: 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HoltWintersAdditive::forecast([10], horizon: -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HoltWintersAdditive::forecast([10], seasonLength: 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HoltWintersAdditive::forecast([10], seasonLength: -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HoltWintersAdditive::forecast([10], alphaBps: -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HoltWintersAdditive::forecast([10], alphaBps: 10_001))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HoltWintersAdditive::forecast([10], betaBps: -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HoltWintersAdditive::forecast([10], betaBps: 10_001))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HoltWintersAdditive::forecast([10], gammaBps: -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HoltWintersAdditive::forecast([10], gammaBps: 10_001))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HoltWintersAdditive::forecast([-5]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SeasonalNaive::forecast([], 7, 7))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SeasonalNaive::forecast([1, 2, 3], 0, 7))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SeasonalNaive::forecast([1, 2, 3], 7, 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SeasonalNaive::forecast([1, 2, 3], 7, 7))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Mape::calculate([], []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Mape::calculate([10], [10, 20]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Mape::calculate([0], [10]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Mape::calculate([10], [-5]))->toThrow(InvalidArgumentException::class);
});

it('tests parameter boundaries 0 and 10000 bps and clamping negative forecast', function (): void {
    $history14 = [10, 20, 10, 20, 10, 20, 10, 10, 20, 10, 20, 10, 20, 10];

    // Boundaries alpha 0 and 10000
    $fcMin = HoltWintersAdditive::forecast($history14, alphaBps: 0, betaBps: 0, gammaBps: 0);
    expect(count($fcMin))->toBe(7);

    $fcMax = HoltWintersAdditive::forecast($history14, alphaBps: 10_000, betaBps: 10_000, gammaBps: 10_000);
    expect(count($fcMax))->toBe(7);

    // Upward trend over 3 seasons
    $trendingHistory = [
        10, 20, 30, 40, 50, 60, 70,
        20, 30, 40, 50, 60, 70, 80,
        30, 40, 50, 60, 70, 80, 90,
    ];
    $trendFc = HoltWintersAdditive::forecast($trendingHistory, horizon: 7);
    expect($trendFc[0])->toBeGreaterThan(30);

    // Negative trend clamping: sharp drop causing negative forecast clamped to 0
    $fallingHistory = [100, 90, 80, 70, 60, 50, 40, 30, 20, 10, 5, 2, 1, 0];
    $clampedFc = HoltWintersAdditive::forecast($fallingHistory, horizon: 10, betaBps: 5000);
    expect($clampedFc[9])->toBe(0);

    // Test exact boundary: 13 days (naive) vs 14 days (HW)
    $h13 = array_slice($history14, 0, 13);
    $fc13 = HoltWintersAdditive::forecast($h13, horizon: 1);
    expect($fc13[0])->toBe($h13[6]); // Naive fallback
});

it('matches required vector: MAPE aktual [100,200] vs ramalan [110,180] -> 1.000 bps', function (): void {
    $mape = Mape::calculate([100, 200], [110, 180]);

    expect($mape)->toBe(1_000);
});

it('matches required vector: pola [100,120,90,110,130,200,180] berulang 4 minggu -> ramalan sama persis (MAPE 0)', function (): void {
    $basePattern = [100, 120, 90, 110, 130, 200, 180];
    $history = [];
    for ($i = 0; $i < 4; $i++) {
        $history = [...$history, ...$basePattern];
    }

    $forecast = HoltWintersAdditive::forecast($history, horizon: 7);

    expect($forecast)->toBe($basePattern);

    $mape = Mape::calculate($basePattern, $forecast);
    expect($mape)->toBe(0);
});

it('falls back to SeasonalNaive when history is shorter than two seasons', function (): void {
    $shortHistory = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100]; // 10 days < 14

    $hwForecast = HoltWintersAdditive::forecast($shortHistory, horizon: 7, seasonLength: 7);
    $naiveForecast = SeasonalNaive::forecast($shortHistory, seasonLength: 7, horizon: 7);

    expect($hwForecast)->toBe($naiveForecast)
        ->and($hwForecast)->toBe([40, 50, 60, 70, 80, 90, 100]);
});

it('satisfies scale invariance property across 500 random cases', function (): void {
    $seed = 20261031_08;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 500; $i++) {
        $k = $randomizer->getInt(2, 5);
        $pattern = [];
        for ($j = 0; $j < 7; $j++) {
            $pattern[] = $randomizer->getInt(50, 500);
        }
        $history = [...$pattern, ...$pattern, ...$pattern];

        $fc1 = HoltWintersAdditive::forecast($history, horizon: 7);

        $scaledHistory = array_map(fn (int $val): int => $val * $k, $history);
        $fc2 = HoltWintersAdditive::forecast($scaledHistory, horizon: 7);

        // fc2 should equal fc1 * k (within integer rounding tolerance <= 1)
        for ($h = 0; $h < 7; $h++) {
            $expected = $fc1[$h] * $k;
            expect(abs($fc2[$h] - $expected))->toBeLessThanOrEqual(1);
        }
    }
});

it('satisfies scale properties across 10.000 random series', function (): void {
    $seed = 20261031_09;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 10_000; $i++) {
        $history = [];
        for ($d = 0; $d < 14; $d++) {
            $history[] = $randomizer->getInt(10, 1000);
        }

        $fc = HoltWintersAdditive::forecast($history, horizon: 3);
        expect(count($fc))->toBe(3)
            ->and($fc[0])->toBeGreaterThanOrEqual(0);
    }
})->group('skala');

it('benchmarks Holt-Winters additive forecasting scalability', function (): void {
    // 90 days (1 quarter) history, 14 days horizon, 50 repeated forecasts
    $history = [];
    for ($i = 0; $i < 90; $i++) {
        $history[] = 100 + ($i % 7) * 20 + ($i % 15);
    }

    $start = microtime(true);
    for ($k = 0; $k < 50; $k++) {
        HoltWintersAdditive::forecast($history, horizon: 14);
    }
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(1.0);
})->group('skala');
