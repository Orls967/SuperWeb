<?php

declare(strict_types=1);

namespace Tests\Unit\Math;

use InvalidArgumentException;
use Modules\Shared\Domain\Math\TieredTariff;
use Modules\Shared\Domain\Math\TieredTariffBlock;

covers(TieredTariff::class);

test('it rejects invalid inputs', function () {
    expect(fn () => new TieredTariffBlock(-1, 100, 1000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TieredTariffBlock(100, 50, 1000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TieredTariffBlock(100, 100, 1000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TieredTariffBlock(0, 100, -10))->toThrow(InvalidArgumentException::class);

    // Negative consumption or TOU
    expect(fn () => TieredTariff::calculate(-1, [new TieredTariffBlock(0, null, 1000)]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TieredTariff::calculate(100, [new TieredTariffBlock(0, null, 1000)], -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TieredTariff::calculate(100, []))->toThrow(InvalidArgumentException::class);

    // First block not starting at 0
    $b1 = new TieredTariffBlock(50, 100, 1000);
    expect(fn () => TieredTariff::calculate(100, [$b1]))->toThrow(InvalidArgumentException::class);

    // Non-contiguous blocks
    $b2 = new TieredTariffBlock(0, 100, 1000);
    $b3 = new TieredTariffBlock(120, null, 2000);
    expect(fn () => TieredTariff::calculate(150, [$b2, $b3]))->toThrow(InvalidArgumentException::class);

    // Non-final block with null maxUnits
    $b4 = new TieredTariffBlock(0, null, 1000);
    $b5 = new TieredTariffBlock(100, null, 2000);
    expect(fn () => TieredTariff::calculate(150, [$b4, $b5]))->toThrow(InvalidArgumentException::class);
});

test('it handles zero consumption gracefully', function () {
    $blocks = [
        new TieredTariffBlock(0, 100, 1000),
        new TieredTariffBlock(100, null, 1500),
    ];

    $res = TieredTariff::calculate(0, $blocks);

    expect($res->totalUnits)->toBe(0)
        ->and($res->totalCost)->toBe(0)
        ->and($res->tierBreakdowns[0]['units'])->toBe(0)
        ->and($res->tierBreakdowns[1]['units'])->toBe(0);
});

test('it matches required vector: blok 0-100 @1.000, 100-300 @1.500, >300 @2.000; konsumsi 350 -> 500.000', function () {
    $blocks = [
        new TieredTariffBlock(0, 100, 1000),
        new TieredTariffBlock(100, 300, 1500),
        new TieredTariffBlock(300, null, 2000),
    ];

    $res = TieredTariff::calculate(350, $blocks);

    expect($res->totalUnits)->toBe(350)
        ->and($res->totalCost)->toBe(500_000)
        ->and(count($res->tierBreakdowns))->toBe(3);

    // Tier 1: 100 units @ 1000 = 100,000
    expect($res->tierBreakdowns[0]['minUnits'])->toBe(0)
        ->and($res->tierBreakdowns[0]['maxUnits'])->toBe(100)
        ->and($res->tierBreakdowns[0]['units'])->toBe(100)
        ->and($res->tierBreakdowns[0]['ratePerUnit'])->toBe(1000)
        ->and($res->tierBreakdowns[0]['baseCost'])->toBe(100_000)
        ->and($res->tierBreakdowns[0]['effectiveCost'])->toBe(100_000);

    // Tier 2: 200 units @ 1500 = 300,000
    expect($res->tierBreakdowns[1]['minUnits'])->toBe(100)
        ->and($res->tierBreakdowns[1]['maxUnits'])->toBe(300)
        ->and($res->tierBreakdowns[1]['units'])->toBe(200)
        ->and($res->tierBreakdowns[1]['ratePerUnit'])->toBe(1500)
        ->and($res->tierBreakdowns[1]['baseCost'])->toBe(300_000)
        ->and($res->tierBreakdowns[1]['effectiveCost'])->toBe(300_000);

    // Tier 3: 50 units @ 2000 = 100,000
    expect($res->tierBreakdowns[2]['minUnits'])->toBe(300)
        ->and($res->tierBreakdowns[2]['maxUnits'])->toBeNull()
        ->and($res->tierBreakdowns[2]['units'])->toBe(50)
        ->and($res->tierBreakdowns[2]['ratePerUnit'])->toBe(2000)
        ->and($res->tierBreakdowns[2]['baseCost'])->toBe(100_000)
        ->and($res->tierBreakdowns[2]['effectiveCost'])->toBe(100_000);
});

test('it applies TOU multipliers correctly', function () {
    $blocks = [
        new TieredTariffBlock(0, 100, 1000),
        new TieredTariffBlock(100, 300, 1500),
        new TieredTariffBlock(300, null, 2000),
    ];

    // Peak hours: 15,000 bps = 1.5x -> 500,000 * 1.5 = 750,000
    $peak = TieredTariff::calculate(350, $blocks, 15_000);
    expect($peak->totalCost)->toBe(750_000);

    // Off-peak hours: 8,000 bps = 0.8x -> 500,000 * 0.8 = 400,000
    $offPeak = TieredTariff::calculate(350, $blocks, 8_000);
    expect($offPeak->totalCost)->toBe(400_000);
});

test('it verifies properties: monotonic, continuous at boundaries, and sum(tier units) = consumption', function () {
    $blocks = [
        new TieredTariffBlock(0, 100, 1000),
        new TieredTariffBlock(100, 300, 1500),
        new TieredTariffBlock(300, null, 2000),
    ];

    $prevCost = -1;

    for ($c = 0; $c <= 500; $c++) {
        $res = TieredTariff::calculate($c, $blocks);

        // 1. Monotonic: cost(c) >= cost(c - 1)
        expect($res->totalCost)->toBeGreaterThanOrEqual($prevCost);
        $prevCost = $res->totalCost;

        // 2. Sum of tier units = consumption
        $sumTierUnits = array_sum(array_column($res->tierBreakdowns, 'units'));
        expect($sumTierUnits)->toBe($c)
            ->and($res->totalUnits)->toBe($c);
    }

    // 3. Continuity at boundaries:
    // Boundary 100: Cost at 99 = 99,000. Cost at 100 = 100,000 (delta = 1000). Cost at 101 = 101,500 (delta = 1500).
    $cost99 = TieredTariff::calculate(99, $blocks)->totalCost;
    $cost100 = TieredTariff::calculate(100, $blocks)->totalCost;
    $cost101 = TieredTariff::calculate(101, $blocks)->totalCost;
    expect($cost100 - $cost99)->toBe(1000)
        ->and($cost101 - $cost100)->toBe(1500);

    // Boundary 300: Cost at 299 -> delta = 1500. Cost at 301 -> delta = 2000.
    $cost299 = TieredTariff::calculate(299, $blocks)->totalCost;
    $cost300 = TieredTariff::calculate(300, $blocks)->totalCost;
    $cost301 = TieredTariff::calculate(301, $blocks)->totalCost;
    expect($cost300 - $cost299)->toBe(1500)
        ->and($cost301 - $cost300)->toBe(2000);
});

test('it scales efficiently for 10000 progressive calculations', function () {
    $blocks = [
        new TieredTariffBlock(0, 100, 1000),
        new TieredTariffBlock(100, 300, 1500),
        new TieredTariffBlock(300, 600, 1800),
        new TieredTariffBlock(600, null, 2500),
    ];

    for ($i = 0; $i < 10_000; $i++) {
        $res = TieredTariff::calculate($i % 1000, $blocks);
        expect($res->totalUnits)->toBe($i % 1000);
    }
})->group('skala');
