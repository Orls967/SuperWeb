<?php

declare(strict_types=1);

use Modules\Shared\Domain\Numerics\PriorityKnapsack;
use Modules\Shared\Domain\Numerics\PriorityKnapsackVerifier;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Independent brute force oracle for tests.
 */
function testBruteForceMaxKW(array $loads, int $capacity): int
{
    $n = count($loads);
    if ($n === 0 || $capacity <= 0) {
        return 0;
    }
    $maxKW = 0;
    $limit = 1 << $n;
    for ($mask = 0; $mask < $limit; $mask++) {
        $currentKW = 0;
        for ($i = 0; $i < $n; $i++) {
            if (($mask & (1 << $i)) !== 0) {
                $currentKW += $loads[$i]['kw'];
            }
        }
        if ($currentKW <= $capacity && $currentKW > $maxKW) {
            $maxKW = $currentKW;
        }
    }

    return $maxKW;
}

test('priority knapsack exact vector: 500 kW capacity serves ICU, ward, pabrik-120 = 470 kW', function () {
    $groups = [
        [
            'group' => 'RS',
            'loads' => [
                ['id' => 'ICU', 'kw' => 200],
                ['id' => 'ward', 'kw' => 150],
            ],
        ],
        [
            'group' => 'pabrik',
            'loads' => [
                ['id' => 'pabrik-120', 'kw' => 120],
                ['id' => 'pabrik-100', 'kw' => 100],
            ],
        ],
        [
            'group' => 'mall',
            'loads' => [
                ['id' => 'mall-90', 'kw' => 90],
                ['id' => 'mall-60', 'kw' => 60],
            ],
        ],
    ];

    $result = PriorityKnapsack::solve(500, $groups);

    expect($result)->toHaveKeys(['total_capacity_kw', 'served_kw', 'remaining_kw', 'served_loads', 'shed_loads', 'certificate']);
    expect($result['certificate'])->toHaveKeys(['capacity_kw', 'served_kw', 'remaining_kw', 'served_count', 'shed_count']);
    expect($result['served_kw'])->toBe(470);
    expect($result['remaining_kw'])->toBe(30);

    $servedIds = array_column($result['served_loads'], 'id');
    expect($servedIds)->toBe(['ICU', 'ward', 'pabrik-120']);

    $shedIds = array_column($result['shed_loads'], 'id');
    expect($shedIds)->toBe(['pabrik-100', 'mall-90', 'mall-60']);

    expect(PriorityKnapsackVerifier::verify(500, $groups, $result))->toBeTrue();
});

test('priority knapsack exact capacity match and boundary', function () {
    $groups = [
        [
            'group' => 'Primary',
            'loads' => [
                ['id' => 'A', 'kw' => 30],
                ['id' => 'B', 'kw' => 20],
            ],
        ],
    ];
    $res = PriorityKnapsack::solve(50, $groups);
    expect($res['served_kw'])->toBe(50);
    expect($res['remaining_kw'])->toBe(0);
    expect($res['served_loads'])->toHaveCount(2);
    expect($res['shed_loads'])->toHaveCount(0);
});

test('priority knapsack handles all loads exceeding capacity', function () {
    $groups = [
        [
            'group' => 'Heavy',
            'loads' => [
                ['id' => 'H1', 'kw' => 50],
                ['id' => 'H2', 'kw' => 60],
            ],
        ],
    ];
    $res = PriorityKnapsack::solve(20, $groups);
    expect($res['served_kw'])->toBe(0);
    expect($res['remaining_kw'])->toBe(20);
    expect($res['shed_loads'])->toHaveCount(2);
    expect(PriorityKnapsackVerifier::verify(20, $groups, $res))->toBeTrue();
});

test('priority knapsack matches brute force oracle for <= 12 loads', function () {
    $loads = [
        ['id' => '1', 'kw' => 12],
        ['id' => '2', 'kw' => 15],
        ['id' => '3', 'kw' => 20],
        ['id' => '4', 'kw' => 25],
    ];
    $groups = [['group' => 'G', 'loads' => $loads]];
    $res = PriorityKnapsack::solve(37, $groups);
    $bf = testBruteForceMaxKW($loads, 37);

    expect($res['served_kw'])->toBe($bf);
    expect($res['served_kw'])->toBe(37);
    expect(PriorityKnapsackVerifier::verify(37, $groups, $res))->toBeTrue();
});

test('priority knapsack solveKnapsackGroup ordering and tie breaks', function () {
    $loads = [
        ['id' => 'A', 'kw' => 10],
        ['id' => 'B', 'kw' => 20],
    ];
    // Both items fit, indices should be returned in original order [0, 1]
    expect(PriorityKnapsack::solveKnapsackGroup($loads, 30))->toBe([0, 1]);
});

test('priority knapsack higher priority group is never sacrificed', function () {
    $groups = [
        [
            'group' => 'RS',
            'loads' => [['id' => 'hospital_main', 'kw' => 500]],
        ],
        [
            'group' => 'Pabrik',
            'loads' => [
                ['id' => 'p1', 'kw' => 100],
                ['id' => 'p2', 'kw' => 100],
            ],
        ],
    ];

    $result = PriorityKnapsack::solve(500, $groups);
    expect($result['served_kw'])->toBe(500);
    expect($result['served_loads'][0]['id'])->toBe('hospital_main');
    expect($result['remaining_kw'])->toBe(0);
});

test('priority knapsack handles zero capacity or empty groups', function () {
    $groups = [
        [
            'group' => 'Mall',
            'loads' => [['id' => 'm1', 'kw' => 50]],
        ],
    ];

    $res = PriorityKnapsack::solve(0, $groups);
    expect($res['served_kw'])->toBe(0);
    expect($res['remaining_kw'])->toBe(0);
    expect($res['shed_loads'])->toHaveCount(1);

    expect(PriorityKnapsack::solve(100, []))->toBe([
        'total_capacity_kw' => 100,
        'served_kw' => 0,
        'remaining_kw' => 100,
        'served_loads' => [],
        'shed_loads' => [],
        'certificate' => [
            'capacity_kw' => 100,
            'served_kw' => 0,
            'remaining_kw' => 100,
            'served_count' => 0,
            'shed_count' => 0,
        ],
    ]);

    expect(PriorityKnapsack::solve(100, [['group' => 'EmptyGroup', 'loads' => []]]))->toBe([
        'total_capacity_kw' => 100,
        'served_kw' => 0,
        'remaining_kw' => 100,
        'served_loads' => [],
        'shed_loads' => [],
        'certificate' => [
            'capacity_kw' => 100,
            'served_kw' => 0,
            'remaining_kw' => 100,
            'served_count' => 0,
            'shed_count' => 0,
        ],
    ]);

    expect(PriorityKnapsack::solveKnapsackGroup([], 100))->toBe([]);
    expect(PriorityKnapsack::solveKnapsackGroup([['id' => 'x', 'kw' => 10]], 0))->toBe([]);
    expect(PriorityKnapsack::solveKnapsackGroup([['id' => 'x', 'kw' => 10]], -1))->toBe([]);
});

test('priority knapsack rejects negative capacity or non-positive load kW', function () {
    expect(fn () => PriorityKnapsack::solve(-10, []))->toThrow(InvalidArgumentException::class);
    expect(fn () => PriorityKnapsack::solve(100, [
        [
            'group' => 'G1',
            'loads' => [['id' => 'x', 'kw' => 0]],
        ],
    ]))->toThrow(InvalidArgumentException::class);
    expect(fn () => PriorityKnapsack::solve(100, [
        [
            'group' => 'G1',
            'loads' => [['id' => 'x', 'kw' => -5]],
        ],
    ]))->toThrow(InvalidArgumentException::class);
});

test('priority knapsack benchmark: 200 loads across 10.000 kW under 2 seconds', function () {
    $rng = new Randomizer(new Xoshiro256StarStar(424242));

    $loads = [];
    for ($i = 0; $i < 200; $i++) {
        $loads[] = ['id' => "load_{$i}", 'kw' => $rng->getInt(10, 200)];
    }

    $groups = [
        ['group' => 'Primary', 'loads' => $loads],
    ];

    $start = microtime(true);
    $result = PriorityKnapsack::solve(10_000, $groups);
    $duration = microtime(true) - $start;

    expect($result['served_kw'])->toBeGreaterThan(9_900);
    expect($duration)->toBeLessThan(2.0);
    expect(PriorityKnapsackVerifier::verify(10_000, $groups, $result))->toBeTrue();
})->group('skala');

test('priority knapsack verifier catches invalid claims', function () {
    $groups = [
        [
            'group' => 'RS',
            'loads' => [['id' => 'ICU', 'kw' => 200]],
        ],
    ];

    $res = PriorityKnapsack::solve(500, $groups);

    // Corrupt served sum
    $c = $res;
    $c['served_kw'] = 199;
    expect(fn () => PriorityKnapsackVerifier::verify(500, $groups, $c))->toThrow(UnexpectedValueException::class);

    // Corrupt remaining
    $c = $res;
    $c['remaining_kw'] = 250;
    expect(fn () => PriorityKnapsackVerifier::verify(500, $groups, $c))->toThrow(UnexpectedValueException::class);

    // Corrupt shed load that strictly fits (10 <= 300)
    $c = $res;
    $c['shed_loads'] = [['group' => 'RS', 'id' => 'small', 'kw' => 10]];
    expect(fn () => PriorityKnapsackVerifier::verify(500, $groups, $c))->toThrow(UnexpectedValueException::class);

    // Corrupt shed load that exactly equals remaining capacity (300 == 300)
    $c = $res;
    $c['shed_loads'] = [['group' => 'RS', 'id' => 'exact', 'kw' => 300]];
    expect(fn () => PriorityKnapsackVerifier::verify(500, $groups, $c))->toThrow(UnexpectedValueException::class);

    // Corrupt re-solve mismatch
    $c = $res;
    $c['served_loads'] = [];
    $c['served_kw'] = 0;
    $c['remaining_kw'] = 500;
    $c['shed_loads'] = [['group' => 'RS', 'id' => 'ICU', 'kw' => 200]];
    expect(fn () => PriorityKnapsackVerifier::verify(500, $groups, $c))->toThrow(UnexpectedValueException::class);
});
