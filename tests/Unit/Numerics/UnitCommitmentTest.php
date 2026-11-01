<?php

declare(strict_types=1);

use Modules\Shared\Domain\Numerics\MeritOrderDispatch;
use Modules\Shared\Domain\Numerics\UnitCommitment;
use Modules\Shared\Domain\Numerics\UnitCommitmentVerifier;

test('merit order dispatch fulfills minimum and fills cheapest marginal units', function () {
    $units = [
        ['code' => 'B', 'min_mw' => 10, 'max_mw' => 50, 'marginal_cost' => 20, 'startup_cost' => 50],
        ['code' => 'A', 'min_mw' => 20, 'max_mw' => 100, 'marginal_cost' => 10, 'startup_cost' => 100],
    ];

    $result = MeritOrderDispatch::dispatch($units, 120);

    expect($result)->not->toBeNull();
    expect($result['dispatches'])->toBe([
        'A' => 100,
        'B' => 20,
    ]);
    expect($result['cost'])->toBe(1400);

    // Empty units with 0 demand
    expect(MeritOrderDispatch::dispatch([], 0))->toBe(['dispatches' => [], 'cost' => 0]);
    // Empty units with positive demand
    expect(MeritOrderDispatch::dispatch([], 10))->toBeNull();
});

test('merit order dispatch rejects invalid inputs or impossible demands', function () {
    $units = [
        ['code' => 'A', 'min_mw' => 10, 'max_mw' => 50, 'marginal_cost' => 10, 'startup_cost' => 10],
    ];

    expect(fn () => MeritOrderDispatch::dispatch($units, -1))->toThrow(InvalidArgumentException::class);
    expect(fn () => MeritOrderDispatch::dispatch([
        ['code' => 'A', 'min_mw' => -1, 'max_mw' => 50, 'marginal_cost' => 10, 'startup_cost' => 10],
    ], 20))->toThrow(InvalidArgumentException::class);
    expect(fn () => MeritOrderDispatch::dispatch([
        ['code' => 'A', 'min_mw' => 50, 'max_mw' => 20, 'marginal_cost' => 10, 'startup_cost' => 10],
    ], 20))->toThrow(InvalidArgumentException::class);
    expect(fn () => MeritOrderDispatch::dispatch([
        ['code' => 'A', 'min_mw' => 10, 'max_mw' => 50, 'marginal_cost' => -10, 'startup_cost' => 10],
    ], 20))->toThrow(InvalidArgumentException::class);

    // Demand below min or above max
    expect(MeritOrderDispatch::dispatch($units, 5))->toBeNull();
    expect(MeritOrderDispatch::dispatch($units, 60))->toBeNull();
});

test('unit commitment exact vector: A, B, C over 3 hours produces 2.850 total cost', function () {
    $units = [
        ['code' => 'A', 'min_mw' => 20, 'max_mw' => 100, 'marginal_cost' => 10, 'startup_cost' => 100],
        ['code' => 'B', 'min_mw' => 10, 'max_mw' => 50, 'marginal_cost' => 20, 'startup_cost' => 50],
        ['code' => 'C', 'min_mw' => 5, 'max_mw' => 40, 'marginal_cost' => 50, 'startup_cost' => 0],
    ];

    $demands = [50, 120, 80];
    $result = UnitCommitment::solve($units, $demands, []);

    expect($result['status'])->toBe('OPTIMAL');
    expect($result['total_cost'])->toBe(2_850);
    expect($result['hourly_schedule'])->toHaveCount(3);

    // Hour 0 (jam 1): {A=50}
    expect($result['hourly_schedule'][0]['online_units'])->toBe(['A']);
    expect($result['hourly_schedule'][0]['dispatches']['A'])->toBe(50);
    expect($result['hourly_schedule'][0]['startup_cost'])->toBe(100);
    expect($result['hourly_schedule'][0]['total_cost'])->toBe(600);

    // Hour 1 (jam 2): {A=100, B=20}
    expect($result['hourly_schedule'][1]['online_units'])->toBe(['A', 'B']);
    expect($result['hourly_schedule'][1]['dispatches']['A'])->toBe(100);
    expect($result['hourly_schedule'][1]['dispatches']['B'])->toBe(20);
    expect($result['hourly_schedule'][1]['startup_cost'])->toBe(50);
    expect($result['hourly_schedule'][1]['total_cost'])->toBe(1450);

    // Hour 2 (jam 3): {A=80}
    expect($result['hourly_schedule'][2]['online_units'])->toBe(['A']);
    expect($result['hourly_schedule'][2]['dispatches']['A'])->toBe(80);
    expect($result['hourly_schedule'][2]['startup_cost'])->toBe(0);
    expect($result['hourly_schedule'][2]['total_cost'])->toBe(800);

    expect(UnitCommitmentVerifier::verify($units, $demands, [], $result))->toBeTrue();
});

test('unit commitment with initial online units transition', function () {
    $units = [
        ['code' => 'A', 'min_mw' => 10, 'max_mw' => 50, 'marginal_cost' => 10, 'startup_cost' => 100],
        ['code' => 'B', 'min_mw' => 10, 'max_mw' => 50, 'marginal_cost' => 20, 'startup_cost' => 100],
    ];

    // A is already online initially, so hour 0 with demand 30 requires no startup cost for A
    $result = UnitCommitment::solve($units, [30], ['A']);
    expect($result['status'])->toBe('OPTIMAL');
    expect($result['hourly_schedule'][0]['startup_cost'])->toBe(0);
    expect(UnitCommitmentVerifier::verify($units, [30], ['A'], $result))->toBeTrue();
});

test('unit commitment brute force optimal method directly tested', function () {
    $units = [
        ['code' => 'A', 'min_mw' => 10, 'max_mw' => 50, 'marginal_cost' => 10, 'startup_cost' => 100],
        ['code' => 'B', 'min_mw' => 10, 'max_mw' => 50, 'marginal_cost' => 20, 'startup_cost' => 50],
    ];
    $opt = UnitCommitmentVerifier::bruteForceOptimal($units, [30, 40], ['A']);
    expect($opt)->toBe(30 * 10 + 40 * 10); // 700
});

test('unit commitment returns infeasible with causing hour when demand exceeds capacity', function () {
    $units = [
        ['code' => 'A', 'min_mw' => 10, 'max_mw' => 50, 'marginal_cost' => 10, 'startup_cost' => 10],
    ];

    $demands = [30, 100, 30]; // hour 1 demands 100 > max 50
    $result = UnitCommitment::solve($units, $demands, []);

    expect($result['status'])->toBe('INFEASIBLE');
    expect($result['causing_hour'])->toBe(1);
    expect($result['hourly_schedule'])->toBeEmpty();
    expect(UnitCommitmentVerifier::verify($units, $demands, [], $result))->toBeTrue();
});

test('unit commitment matches brute force for random small instances', function () {
    $units = [
        ['code' => 'G1', 'min_mw' => 10, 'max_mw' => 50, 'marginal_cost' => 15, 'startup_cost' => 80],
        ['code' => 'G2', 'min_mw' => 20, 'max_mw' => 70, 'marginal_cost' => 10, 'startup_cost' => 150],
        ['code' => 'G3', 'min_mw' => 5, 'max_mw' => 30, 'marginal_cost' => 25, 'startup_cost' => 20],
    ];

    $demands = [40, 80, 110, 60];
    $result = UnitCommitment::solve($units, $demands, ['G1']);

    expect($result['status'])->toBe('OPTIMAL');
    expect(UnitCommitmentVerifier::verify($units, $demands, ['G1'], $result))->toBeTrue();
});

test('unit commitment benchmark: 8 units across 24 hours under 3 seconds', function () {
    $units = [
        ['code' => 'U1', 'min_mw' => 50, 'max_mw' => 200, 'marginal_cost' => 10, 'startup_cost' => 500],
        ['code' => 'U2', 'min_mw' => 40, 'max_mw' => 150, 'marginal_cost' => 15, 'startup_cost' => 400],
        ['code' => 'U3', 'min_mw' => 30, 'max_mw' => 120, 'marginal_cost' => 20, 'startup_cost' => 300],
        ['code' => 'U4', 'min_mw' => 20, 'max_mw' => 100, 'marginal_cost' => 25, 'startup_cost' => 200],
        ['code' => 'U5', 'min_mw' => 10, 'max_mw' => 80, 'marginal_cost' => 30, 'startup_cost' => 100],
        ['code' => 'U6', 'min_mw' => 10, 'max_mw' => 60, 'marginal_cost' => 35, 'startup_cost' => 50],
        ['code' => 'U7', 'min_mw' => 5, 'max_mw' => 40, 'marginal_cost' => 40, 'startup_cost' => 20],
        ['code' => 'U8', 'min_mw' => 5, 'max_mw' => 30, 'marginal_cost' => 50, 'startup_cost' => 10],
    ];

    $demands = [
        150, 140, 130, 120, 110, 130, 200, 350, 450, 500, 550, 600,
        580, 560, 540, 520, 500, 530, 570, 520, 400, 300, 220, 180,
    ];

    $start = microtime(true);
    $result = UnitCommitment::solve($units, $demands, ['U1', 'U2']);
    $duration = microtime(true) - $start;

    expect($result['status'])->toBe('OPTIMAL');
    expect($duration)->toBeLessThan(3.0);
    expect(UnitCommitmentVerifier::verify($units, $demands, ['U1', 'U2'], $result))->toBeTrue();
})->group('skala');

test('unit commitment rejects invalid inputs', function () {
    expect(fn () => UnitCommitment::solve([], [10]))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnitCommitment::solve([
        ['code' => 'A', 'min_mw' => 1, 'max_mw' => 2, 'marginal_cost' => 1, 'startup_cost' => 1],
    ], []))->toThrow(InvalidArgumentException::class);

    // Negative unit parameters
    expect(fn () => UnitCommitment::solve([
        ['code' => 'A', 'min_mw' => -1, 'max_mw' => 2, 'marginal_cost' => 1, 'startup_cost' => 1],
    ], [1]))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnitCommitment::solve([
        ['code' => 'A', 'min_mw' => 10, 'max_mw' => 5, 'marginal_cost' => 1, 'startup_cost' => 1],
    ], [1]))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnitCommitment::solve([
        ['code' => 'A', 'min_mw' => 1, 'max_mw' => 5, 'marginal_cost' => -1, 'startup_cost' => 1],
    ], [1]))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnitCommitment::solve([
        ['code' => 'A', 'min_mw' => 1, 'max_mw' => 5, 'marginal_cost' => 1, 'startup_cost' => -1],
    ], [1]))->toThrow(InvalidArgumentException::class);

    // Duplicate code
    expect(fn () => UnitCommitment::solve([
        ['code' => 'A', 'min_mw' => 1, 'max_mw' => 2, 'marginal_cost' => 1, 'startup_cost' => 1],
        ['code' => 'A', 'min_mw' => 2, 'max_mw' => 4, 'marginal_cost' => 1, 'startup_cost' => 1],
    ], [2]))->toThrow(InvalidArgumentException::class);

    // Unknown initial unit
    expect(fn () => UnitCommitment::solve([
        ['code' => 'A', 'min_mw' => 1, 'max_mw' => 2, 'marginal_cost' => 1, 'startup_cost' => 1],
    ], [1], ['UNKNOWN']))->toThrow(InvalidArgumentException::class);

    // Negative demand
    expect(fn () => UnitCommitment::solve([
        ['code' => 'A', 'min_mw' => 1, 'max_mw' => 2, 'marginal_cost' => 1, 'startup_cost' => 1],
    ], [-5]))->toThrow(InvalidArgumentException::class);

    // > 8 units
    $tooMany = [];
    for ($i = 0; $i < 9; $i++) {
        $tooMany[] = ['code' => "U{$i}", 'min_mw' => 1, 'max_mw' => 10, 'marginal_cost' => 1, 'startup_cost' => 1];
    }
    expect(fn () => UnitCommitment::solve($tooMany, [5]))->toThrow(InvalidArgumentException::class);
});

test('unit commitment verifier detects corrupted results', function () {
    $units = [
        ['code' => 'A', 'min_mw' => 10, 'max_mw' => 50, 'marginal_cost' => 10, 'startup_cost' => 50],
    ];
    $demands = [30];
    $res = UnitCommitment::solve($units, $demands, []);

    // Corrupt status
    $c = $res;
    $c['status'] = 'STRANGE';
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $c))->toThrow(UnexpectedValueException::class);

    // Corrupt schedule length
    $c = $res;
    $c['hourly_schedule'] = [];
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $c))->toThrow(UnexpectedValueException::class);

    // Corrupt demand
    $c = $res;
    $c['hourly_schedule'][0]['demand'] = 999;
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $c))->toThrow(UnexpectedValueException::class);

    // Corrupt unit code
    $c = $res;
    $c['hourly_schedule'][0]['online_units'] = ['Z'];
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $c))->toThrow(UnexpectedValueException::class);

    // Corrupt bounds
    $c = $res;
    $c['hourly_schedule'][0]['dispatches']['A'] = 5;
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $c))->toThrow(UnexpectedValueException::class);

    // Corrupt sum
    $c = $res;
    $c['hourly_schedule'][0]['dispatches']['A'] = 25;
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $c))->toThrow(UnexpectedValueException::class);

    // Corrupt dispatch cost
    $c = $res;
    $c['hourly_schedule'][0]['dispatch_cost'] = 999;
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $c))->toThrow(UnexpectedValueException::class);

    // Corrupt startup cost
    $c = $res;
    $c['hourly_schedule'][0]['startup_cost'] = 0;
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $c))->toThrow(UnexpectedValueException::class);

    // Corrupt hour total
    $c = $res;
    $c['hourly_schedule'][0]['total_cost'] = 9999;
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $c))->toThrow(UnexpectedValueException::class);

    // Corrupt overall total
    $c = $res;
    $c['total_cost'] = 12345;
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $c))->toThrow(UnexpectedValueException::class);

    // Corrupt brute force comparison
    $c = $res;
    $c['total_cost'] = 1; // impossible optimal
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $c))->toThrow(UnexpectedValueException::class);

    // Corrupt infeasible claim when actually feasible
    $fakeInfeasible = ['status' => 'INFEASIBLE', 'causing_hour' => 0];
    expect(fn () => UnitCommitmentVerifier::verify($units, $demands, [], $fakeInfeasible))->toThrow(UnexpectedValueException::class);
});
