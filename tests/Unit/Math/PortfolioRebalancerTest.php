<?php

declare(strict_types=1);

namespace Tests\Unit\Math;

use InvalidArgumentException;
use Modules\Shared\Domain\Math\PortfolioAsset;
use Modules\Shared\Domain\Math\PortfolioRebalancer;

covers(PortfolioRebalancer::class);

test('it rejects invalid inputs', function () {
    expect(fn () => new PortfolioAsset('', 10, 1000, 1, 5000, 500))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PortfolioAsset('A', -1, 1000, 1, 5000, 500))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PortfolioAsset('A', 10, 0, 1, 5000, 500))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PortfolioAsset('A', 10, 1000, 0, 5000, 500))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PortfolioAsset('A', 10, 1000, 1, 10001, 500))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PortfolioAsset('A', 10, 1000, 1, -1, 500))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PortfolioAsset('A', 10, 1000, 1, 5000, -1))->toThrow(InvalidArgumentException::class);

    // Negative cash
    expect(fn () => PortfolioRebalancer::rebalance(-100, []))->toThrow(InvalidArgumentException::class);

    // Duplicate asset IDs
    $a1 = new PortfolioAsset('A', 10, 1000, 1, 5000, 500);
    $a2 = new PortfolioAsset('A', 20, 1000, 1, 5000, 500);
    expect(fn () => PortfolioRebalancer::rebalance(1000, [$a1, $a2]))->toThrow(InvalidArgumentException::class);

    // Total target Bps > 10000
    $b1 = new PortfolioAsset('A', 0, 1000, 1, 6000, 500);
    $b2 = new PortfolioAsset('B', 0, 1000, 1, 5000, 500);
    expect(fn () => PortfolioRebalancer::rebalance(1000, [$b1, $b2]))->toThrow(InvalidArgumentException::class);
});

test('it handles empty or zero total value gracefully', function () {
    $res = PortfolioRebalancer::rebalance(0, []);
    expect($res->remainingCash)->toBe(0)
        ->and($res->units)->toBe([])
        ->and($res->orders)->toBe([])
        ->and($res->initialTotalValue)->toBe(0)
        ->and($res->finalTotalValue)->toBe(0);

    $a = new PortfolioAsset('A', 0, 10000, 1, 10000, 500);
    $res2 = PortfolioRebalancer::rebalance(0, [$a]);
    expect($res2->remainingCash)->toBe(0)
        ->and($res2->units['A'])->toBe(0)
        ->and($res2->orders)->toBe([]);
});

test('it matches required vector 1: kas 1.000.000, bobot 6000/4000, NAV A 10.000, B 25.000, lot 1 -> A 60, B 16, sisa kas 0', function () {
    $assetA = new PortfolioAsset('A', 0, 10_000, 1, 6_000, 500);
    $assetB = new PortfolioAsset('B', 0, 25_000, 1, 4_000, 500);

    $res = PortfolioRebalancer::rebalance(1_000_000, [$assetA, $assetB]);

    expect($res->units['A'])->toBe(60)
        ->and($res->units['B'])->toBe(16)
        ->and($res->remainingCash)->toBe(0)
        ->and($res->initialTotalValue)->toBe(1_000_000)
        ->and($res->finalTotalValue)->toBe(1_000_000)
        ->and($res->finalWeightsBps['A'])->toBe(6_000)
        ->and($res->finalWeightsBps['B'])->toBe(4_000)
        ->and(count($res->orders))->toBe(2);

    expect($res->orders[0]['id'])->toBe('A')
        ->and($res->orders[0]['action'])->toBe('BUY')
        ->and($res->orders[0]['lots'])->toBe(60)
        ->and($res->orders[0]['units'])->toBe(60)
        ->and($res->orders[0]['pricePerUnit'])->toBe(10_000)
        ->and($res->orders[0]['totalAmount'])->toBe(600_000)
        ->and($res->orders[1]['id'])->toBe('B')
        ->and($res->orders[1]['action'])->toBe('BUY')
        ->and($res->orders[1]['lots'])->toBe(16)
        ->and($res->orders[1]['units'])->toBe(16)
        ->and($res->orders[1]['pricePerUnit'])->toBe(25_000)
        ->and($res->orders[1]['totalAmount'])->toBe(400_000);
});

test('it matches required vector 2: NAV B 30.000 -> B 13 unit, sisa kas 10.000', function () {
    $assetA = new PortfolioAsset('A', 0, 10_000, 1, 6_000, 500);
    $assetB = new PortfolioAsset('B', 0, 30_000, 1, 4_000, 500);

    $res = PortfolioRebalancer::rebalance(1_000_000, [$assetA, $assetB]);

    expect($res->units['A'])->toBe(60)
        ->and($res->units['B'])->toBe(13)
        ->and($res->remainingCash)->toBe(10_000)
        ->and($res->finalTotalValue)->toBe(1_000_000)
        ->and($res->finalWeightsBps['A'])->toBe(6_000)
        ->and($res->finalWeightsBps['B'])->toBe(3_900);
});

test('it tie-breaks identical deficit by asset ID ascending', function () {
    // Total portfolio value: 10,000 cash + 10,000 A + 10,000 B + 10,000 C = 40,000.
    // Target A (5000 bps) = 20,000. Current A = 1 unit (10,000). Deficit A = 10,000.
    // Target B (5000 bps) = 20,000. Current B = 1 unit (10,000). Deficit B = 10,000.
    // Target C (0 bps, band 5000 bps) = 0. Current C = 1 unit (2500 bps <= band). Not sold.
    // Available cash = 10,000 (enough for exactly 1 lot).
    // Tie-break ID ascending means asset A is bought, exhausting cash!
    $assetA = new PortfolioAsset('A', 1, 10_000, 1, 5_000, 500);
    $assetB = new PortfolioAsset('B', 1, 10_000, 1, 5_000, 500);
    $assetC = new PortfolioAsset('C', 1, 10_000, 1, 0, 5_000);

    $res = PortfolioRebalancer::rebalance(10_000, [$assetC, $assetB, $assetA]);

    expect($res->units['A'])->toBe(2)
        ->and($res->units['B'])->toBe(1)
        ->and($res->units['C'])->toBe(1)
        ->and($res->remainingCash)->toBe(0)
        ->and(count($res->orders))->toBe(1)
        ->and($res->orders[0]['id'])->toBe('A');
});

test('it sells overweight assets exceeding upper band down to target', function () {
    // Portfolio starts with 100 units of A @ 10,000 = 1,000,000 (100% weight)
    // Target A: 5000 bps (50%), band: 1000 bps (upper band 6000 bps)
    // Target B: 5000 bps (50%), band: 1000 bps, initially 0 units @ 10,000
    // Total value: 1,000,000. Target value A: 500,000 (50 units). Excess value: 500,000.
    // Sells 50 units of A -> 500,000 cash.
    // Then greedy buys 50 units of B @ 10,000 with 500,000 cash -> 50 units B, 0 cash.
    $assetA = new PortfolioAsset('A', 100, 10_000, 1, 5_000, 1_000);
    $assetB = new PortfolioAsset('B', 0, 10_000, 1, 5_000, 1_000);

    $res = PortfolioRebalancer::rebalance(0, [$assetA, $assetB]);

    expect($res->units['A'])->toBe(50)
        ->and($res->units['B'])->toBe(50)
        ->and($res->remainingCash)->toBe(0)
        ->and(count($res->orders))->toBe(2);

    $sellOrder = $res->orders[0];
    expect($sellOrder['id'])->toBe('A')
        ->and($sellOrder['action'])->toBe('SELL')
        ->and($sellOrder['lots'])->toBe(50)
        ->and($sellOrder['units'])->toBe(50)
        ->and($sellOrder['pricePerUnit'])->toBe(10_000)
        ->and($sellOrder['totalAmount'])->toBe(500_000);

    $buyOrder = $res->orders[1];
    expect($buyOrder['id'])->toBe('B')
        ->and($buyOrder['action'])->toBe('BUY')
        ->and($buyOrder['lots'])->toBe(50)
        ->and($buyOrder['units'])->toBe(50)
        ->and($buyOrder['pricePerUnit'])->toBe(10_000)
        ->and($buyOrder['totalAmount'])->toBe(500_000);
});

test('it respects lot sizes during selling and buying', function () {
    // Lot size = 100
    // Cash: 1,000,000, NAV A: 1,000, lot: 100 -> price per lot = 100,000
    // NAV B: 2,000, lot: 100 -> price per lot = 200,000
    // Target A: 6000 bps (600,000) -> 600 units (6 lots)
    // Target B: 4000 bps (400,000) -> 200 units (2 lots)
    $assetA = new PortfolioAsset('A', 0, 1_000, 100, 6_000, 500);
    $assetB = new PortfolioAsset('B', 0, 2_000, 100, 4_000, 500);

    $res = PortfolioRebalancer::rebalance(1_000_000, [$assetA, $assetB]);

    expect($res->units['A'])->toBe(600)
        ->and($res->units['B'])->toBe(200)
        ->and($res->remainingCash)->toBe(0);
});

test('it satisfies required properties: never overspend, never negative units, no affordable deficit lot left, deterministic', function () {
    mt_srand(9999);

    for ($iter = 0; $iter < 100; $iter++) {
        $cash = mt_rand(100_000, 5_000_000);
        $numAssets = mt_rand(2, 5);

        // Generate target weights summing to 10000
        $weights = [];
        $remainingWeight = 10_000;
        for ($i = 0; $i < $numAssets - 1; $i++) {
            $w = mt_rand(500, intdiv($remainingWeight, 2));
            $weights[] = $w;
            $remainingWeight -= $w;
        }
        $weights[] = $remainingWeight;

        $assets = [];
        foreach ($weights as $idx => $w) {
            $id = 'AST_'.chr(65 + $idx);
            $nav = mt_rand(1_000, 50_000);
            $lot = mt_rand(1, 5) * 10;
            $initialUnits = mt_rand(0, 10) * $lot;
            $band = mt_rand(200, 1000);

            $assets[] = new PortfolioAsset($id, $initialUnits, $nav, $lot, $w, $band);
        }

        $res1 = PortfolioRebalancer::rebalance($cash, $assets);

        // 1. Never overspend
        expect($res1->remainingCash)->toBeGreaterThanOrEqual(0);

        // 2. Never negative units
        foreach ($res1->units as $id => $u) {
            expect($u)->toBeGreaterThanOrEqual(0);
        }

        // 3. No affordable deficit lot left:
        $totalVal = $res1->finalTotalValue;
        foreach ($assets as $asset) {
            $curVal = $res1->units[$asset->id] * $asset->nav;
            $targetVal = intdiv($totalVal * $asset->targetBps, 10_000);
            $deficit = $targetVal - $curVal;
            $lotPrice = $asset->nav * $asset->lot;

            if ($res1->remainingCash >= $lotPrice) {
                // If we have enough cash for a lot of this asset, its deficit MUST be < lotPrice!
                expect($deficit)->toBeLessThan($lotPrice);
            }
        }

        // 4. Determinism: shuffle inputs and assert identical results
        $shuffled = $assets;
        shuffle($shuffled);
        $res2 = PortfolioRebalancer::rebalance($cash, $shuffled);

        expect($res2->units)->toBe($res1->units)
            ->and($res2->remainingCash)->toBe($res1->remainingCash)
            ->and($res2->finalTotalValue)->toBe($res1->finalTotalValue);
    }
});
