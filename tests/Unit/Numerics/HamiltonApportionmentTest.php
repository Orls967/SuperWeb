<?php

declare(strict_types=1);

use Modules\Shared\Domain\Numerics\HamiltonApportionment;
use Modules\Shared\Domain\Numerics\HamiltonApportionmentVerifier;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

test('hamilton apportionment exact vector: 1.000.000 with 1:1:1 weights', function () {
    $weights = ['A' => 1, 'B' => 1, 'C' => 1];
    $result = HamiltonApportionment::apportion(1_000_000, $weights);

    expect($result)->toBe([
        'A' => 333_334,
        'B' => 333_333,
        'C' => 333_333,
    ]);
    expect(array_sum($result))->toBe(1_000_000);
});

test('hamilton apportionment prioritizes larger remainders before tie-break', function () {
    // Total: 10, Weights: A=10, B=20, C=30 (sum=60)
    // A: 100/60 = 1 rem 40
    // B: 200/60 = 3 rem 20
    // C: 300/60 = 5 rem 0
    // Floors: A=1, B=3, C=5 (sum 9, surplus 1).
    // A has remainder 40 > B (20) > C (0). A gets the surplus 1.
    $result = HamiltonApportionment::apportion(10, ['A' => 10, 'B' => 20, 'C' => 30]);

    expect($result)->toBe([
        'A' => 2,
        'B' => 3,
        'C' => 5,
    ]);
});

test('hamilton apportionment tie break when remainders are identical uses ascending key', function () {
    // Total 1, Weights: Z=1, A=1 (sum 2)
    // Z: 1/2 = 0 rem 1
    // A: 1/2 = 0 rem 1
    // Surplus 1. Tie-break: 'A' < 'Z', so 'A' gets the +1
    $result = HamiltonApportionment::apportion(1, ['Z' => 1, 'A' => 1]);

    expect($result)->toBe([
        'Z' => 0,
        'A' => 1,
    ]);
});

test('hamilton apportionment handles zero total and empty weights', function () {
    expect(HamiltonApportionment::apportion(0, ['A' => 10, 'B' => 20]))->toBe(['A' => 0, 'B' => 0]);
    expect(HamiltonApportionment::apportion(0, ['A' => 0, 'B' => 0]))->toBe(['A' => 0, 'B' => 0]);
    expect(HamiltonApportionment::apportion(100, []))->toBe([]);
});

test('hamilton apportionment rejects negative total amount', function () {
    HamiltonApportionment::apportion(-1, ['A' => 1]);
})->throws(InvalidArgumentException::class, 'Total amount to apportion cannot be negative.');

test('hamilton apportionment rejects negative weights', function () {
    HamiltonApportionment::apportion(100, ['A' => 1, 'B' => -1]);
})->throws(InvalidArgumentException::class, "Weight for 'B' cannot be negative.");

test('hamilton apportionment rejects zero total weight with positive amount', function () {
    HamiltonApportionment::apportion(100, ['A' => 0, 'B' => 0]);
})->throws(InvalidArgumentException::class, 'Total weight must be positive when total amount is greater than zero.');

test('hamilton apportionment property tests with seed determinism', function () {
    $seed = 133742;
    $rng = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 50; $i++) {
        $n = $rng->getInt(1, 10);
        $total = $rng->getInt(0, 100_000);
        $weights = [];
        for ($j = 0; $j < $n; $j++) {
            $weights["item_{$j}"] = $rng->getInt(1, 100);
        }

        $res = HamiltonApportionment::apportion($total, $weights);

        expect(array_sum($res))->toBe($total);
        foreach ($res as $val) {
            expect($val)->toBeGreaterThanOrEqual(0);
        }
        expect(HamiltonApportionmentVerifier::verify($total, $weights, $res))->toBeTrue();
    }
});

test('hamilton apportionment scale property tests', function () {
    $seed = 133742;
    $rng = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 500; $i++) {
        $n = $rng->getInt(1, 10);
        $total = $rng->getInt(0, 10_000_000);
        $weights = [];
        for ($j = 0; $j < $n; $j++) {
            $weights["item_{$j}"] = $rng->getInt(1, 100);
        }

        $res = HamiltonApportionment::apportion($total, $weights);
        expect(array_sum($res))->toBe($total);
    }
})->group('skala');

test('hamilton apportionment verifier detects corrupted sum or keys', function () {
    expect(fn () => HamiltonApportionmentVerifier::verify(100, ['A' => 1, 'B' => 1], ['A' => 50, 'B' => 49]))
        ->toThrow(UnexpectedValueException::class);

    expect(fn () => HamiltonApportionmentVerifier::verify(100, ['A' => 1, 'B' => 1], ['A' => 50, 'C' => 50]))
        ->toThrow(UnexpectedValueException::class);

    expect(fn () => HamiltonApportionmentVerifier::verify(100, ['A' => 1, 'B' => 1], ['A' => -10, 'B' => 110]))
        ->toThrow(UnexpectedValueException::class);

    expect(fn () => HamiltonApportionmentVerifier::verify(100, ['A' => 1, 'B' => 1], ['A' => 100]))
        ->toThrow(UnexpectedValueException::class);

    expect(fn () => HamiltonApportionmentVerifier::verify(0, ['A' => 1], ['A' => 0]))
        ->not->toThrow(UnexpectedValueException::class);
});
