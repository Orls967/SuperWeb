<?php

declare(strict_types=1);

namespace Tests\Unit\Math;

use InvalidArgumentException;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use Modules\Shared\Domain\Math\RouteCertificate;
use Modules\Shared\Domain\Math\RouteCertificateVerifier;
use Modules\Shared\Domain\Math\RouteOptimizer;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(RouteOptimizer::class, RouteCertificateVerifier::class);

it('rejects invalid inputs', function (): void {
    expect(fn () => RouteOptimizer::optimize([]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RouteOptimizer::optimize([[0, 10]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RouteOptimizer::optimize([[5, 10], [10, 0]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RouteOptimizer::optimize([[0, -5], [-5, 0]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RouteOptimizer::optimize([[0, 10], [20, 0]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RouteOptimizer::optimize([[0, 10, 20], [10, 0, -5], [20, -5, 0]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RouteOptimizer::optimize([[0, 10, 20], [10, 0, 15], [25, 15, 0]]))->toThrow(InvalidArgumentException::class);
});

it('handles trivial sizes n=1, n=2, and n=3 with canonical orientation', function (): void {
    $res1 = RouteOptimizer::optimize([[0]]);
    expect($res1->tour)->toBe([0, 0])
        ->and($res1->cost)->toBe(0)
        ->and($res1->lowerBound)->toBe(0)
        ->and($res1->gapBps)->toBe(0)
        ->and($res1->isExact)->toBeTrue()
        ->and($res1->certificate->n)->toBe(1)
        ->and($res1->certificate->tour)->toBe([0, 0])
        ->and($res1->certificate->cost)->toBe(0)
        ->and($res1->certificate->lowerBound)->toBe(0)
        ->and($res1->certificate->gapBps)->toBe(0)
        ->and($res1->certificate->isExact)->toBeTrue();

    $res2 = RouteOptimizer::optimize([
        [0, 15],
        [15, 0],
    ]);
    expect($res2->tour)->toBe([0, 1, 0])
        ->and($res2->cost)->toBe(30)
        ->and($res2->lowerBound)->toBe(30)
        ->and($res2->gapBps)->toBe(0)
        ->and($res2->isExact)->toBeTrue()
        ->and($res2->certificate->n)->toBe(2)
        ->and($res2->certificate->tour)->toBe([0, 1, 0])
        ->and($res2->certificate->cost)->toBe(30);

    // n = 3: Triangle
    $d3 = [
        [0, 10, 20],
        [10, 0, 15],
        [20, 15, 0],
    ];
    $res3 = RouteOptimizer::optimize($d3);
    expect($res3->cost)->toBe(45)
        ->and($res3->tour)->toBe([0, 1, 2, 0])
        ->and($res3->isExact)->toBeTrue()
        ->and($res3->certificate->n)->toBe(3)
        ->and($res3->certificate->cost)->toBe(45);

    // Canonical orientation: forces tour[1] <= tour[n-1]
    $d3Rev = [
        [0, 20, 10],
        [20, 0, 15],
        [10, 15, 0],
    ];
    $res3Rev = RouteOptimizer::optimize($d3Rev);
    expect($res3Rev->cost)->toBe(45)
        ->and($res3Rev->tour)->toBe([0, 1, 2, 0]);
});

it('matches required vector: d 4x4 -> biaya 80 (0-1-3-2-0)', function (): void {
    $d = [
        [0, 10, 15, 20],
        [10, 0, 35, 25],
        [15, 35, 0, 30],
        [20, 25, 30, 0],
    ];

    $result = RouteOptimizer::optimize($d);

    expect($result->cost)->toBe(80)
        ->and($result->lowerBound)->toBe(80)
        ->and($result->gapBps)->toBe(0)
        ->and($result->tour)->toBe([0, 1, 3, 2, 0])
        ->and($result->isExact)->toBeTrue()
        ->and($result->certificate->n)->toBe(4)
        ->and($result->certificate->cost)->toBe(80)
        ->and($result->certificate->isExact)->toBeTrue();
});

it('matches brute-force search for small n (n <= 6)', function (): void {
    $seed = 20261031_10;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    for ($case = 0; $case < 20; $case++) {
        $n = 5;
        $dist = array_fill(0, $n, array_fill(0, $n, 0));
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $val = $randomizer->getInt(5, 100);
                $dist[$i][$j] = $val;
                $dist[$j][$i] = $val;
            }
        }

        $hkResult = RouteOptimizer::optimize($dist);

        // Brute force all permutations of (1..4)
        $minBrute = PHP_INT_MAX;
        $permutations = [[1, 2, 3, 4], [1, 2, 4, 3], [1, 3, 2, 4], [1, 3, 4, 2], [1, 4, 2, 3], [1, 4, 3, 2],
            [2, 1, 3, 4], [2, 1, 4, 3], [2, 3, 1, 4], [2, 3, 4, 1], [2, 4, 1, 3], [2, 4, 3, 1],
            [3, 1, 2, 4], [3, 1, 4, 2], [3, 2, 1, 4], [3, 2, 4, 1], [3, 4, 1, 2], [3, 4, 2, 1],
            [4, 1, 2, 3], [4, 1, 3, 2], [4, 2, 1, 3], [4, 2, 3, 1], [4, 3, 1, 2], [4, 3, 2, 1]];

        foreach ($permutations as $perm) {
            $cost = $dist[0][$perm[0]] + $dist[$perm[0]][$perm[1]] + $dist[$perm[1]][$perm[2]] + $dist[$perm[2]][$perm[3]] + $dist[$perm[3]][0];
            if ($cost < $minBrute) {
                $minBrute = $cost;
            }
        }

        expect($hkResult->cost)->toBe($minBrute);
    }
});

it('enforces certificate verification and rejects tampered certificates', function (): void {
    $d = [
        [0, 10, 15, 20],
        [10, 0, 35, 25],
        [15, 35, 0, 30],
        [20, 25, 30, 0],
    ];
    $result = RouteOptimizer::optimize($d);

    RouteCertificateVerifier::verify($result->certificate);

    // Tampered cost
    $tamperedCost = new RouteCertificate(4, $result->tour, 79, 80, 0, true, $d);
    expect(fn () => RouteCertificateVerifier::verify($tamperedCost))->toThrow(InvalidCertificateException::class);

    // Tampered tour missing node
    $tamperedTour = new RouteCertificate(4, [0, 1, 1, 2, 0], 80, 80, 0, true, $d);
    expect(fn () => RouteCertificateVerifier::verify($tamperedTour))->toThrow(InvalidCertificateException::class);
});

it('computes 1-tree MST lower bound directly and verifies exact values', function (): void {
    // n = 1
    expect(RouteOptimizer::compute1TreeLowerBound([[0]], 1))->toBe(0);

    // n = 2
    expect(RouteOptimizer::compute1TreeLowerBound([[0, 25], [25, 0]], 2))->toBe(50);

    // n = 3: Triangle with edges 10, 15, 20
    $d3 = [
        [0, 10, 20],
        [10, 0, 15],
        [20, 15, 0],
    ];
    // MST on {1, 2} is edge (1,2) = 15. Cheapest from 0: 10, 20. Total = 15 + 10 + 20 = 45.
    expect(RouteOptimizer::compute1TreeLowerBound($d3, 3))->toBe(45);

    // n = 4: Vector matrix
    $d4 = [
        [0, 10, 15, 20],
        [10, 0, 35, 25],
        [15, 35, 0, 30],
        [20, 25, 30, 0],
    ];
    // MST on {1, 2, 3} has edges (1,3)=25 and (2,3)=30 -> 55. Cheapest from 0: 10, 15. Total = 55 + 25 = 80.
    expect(RouteOptimizer::compute1TreeLowerBound($d4, 4))->toBe(80);

    // n = 5
    $d5 = [
        [0, 10, 20, 30, 40],
        [10, 0, 5, 15, 25],
        [20, 5, 0, 35, 45],
        [30, 15, 35, 0, 50],
        [40, 25, 45, 50, 0],
    ];
    // MST on {1, 2, 3, 4}:
    // edges: (1,2)=5, (1,3)=15, (1,4)=25 -> sum = 45.
    // Cheapest from 0: 10, 20.
    // Total = 45 + 10 + 20 = 75.
    expect(RouteOptimizer::compute1TreeLowerBound($d5, 5))->toBe(75);

    // Prim MST edge-update verification: edge (2,3) = 1 improves over (1,3) = 100
    $dPrim = [
        [0, 10, 20, 30],
        [10, 0, 100, 100],
        [20, 100, 0, 1],
        [30, 100, 1, 0],
    ];
    expect(RouteOptimizer::compute1TreeLowerBound($dPrim, 4))->toBe(131);
});

it('exercises 2-opt local search optimization and lower bound gap calculation', function (): void {
    // 14 vertices where Nearest Neighbor creates crossings that 2-opt untangles
    $coords = [
        [0, 0],
        [500, 100],
        [100, 0],
        [600, 100],
        [200, 0],
        [700, 100],
        [300, 0],
        [800, 100],
        [400, 0],
        [900, 100],
        [1000, 0],
        [1100, 100],
        [1200, 0],
        [1300, 100],
    ];
    $n = count($coords);
    $dist = array_fill(0, $n, array_fill(0, $n, 0));
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $dx = $coords[$i][0] - $coords[$j][0];
            $dy = $coords[$i][1] - $coords[$j][1];
            $d = (int) round(sqrt($dx * $dx + $dy * $dy));
            $dist[$i][$j] = $d;
            $dist[$j][$i] = $d;
        }
    }

    $result = RouteOptimizer::optimize($dist);

    expect($result->isExact)->toBeFalse()
        ->and($result->lowerBound)->toBe(1705)
        ->and($result->cost)->toBe(2651)
        ->and($result->tour)->toBe([0, 2, 4, 6, 8, 10, 12, 13, 11, 9, 7, 5, 3, 1, 0])
        ->and($result->cost)->toBeLessThan(2809); // 2-opt strictly untangled NN tour (2809 -> 2651)

    $expectedGapBps = intdiv(($result->cost - $result->lowerBound) * 10_000, $result->lowerBound);
    expect($result->gapBps)->toBe($expectedGapBps)
        ->and($result->certificate->gapBps)->toBe($expectedGapBps)
        ->and($result->certificate->n)->toBe(14)
        ->and($result->certificate->lowerBound)->toBe(1705)
        ->and($result->certificate->cost)->toBe(2651);
});

it('verifies Held-Karp tie-breaking and canonical orientation on symmetric square', function (): void {
    $dSquare = [
        [0, 1, 2, 1],
        [1, 0, 1, 2],
        [2, 1, 0, 1],
        [1, 2, 1, 0],
    ];
    $result = RouteOptimizer::optimize($dSquare);
    expect($result->isExact)->toBeTrue()
        ->and($result->cost)->toBe(4)
        ->and($result->tour)->toBe([0, 1, 2, 3, 0]);
});

it('solves 1D points on a line using heuristic with lower bound', function (): void {
    $n = 13;
    $dist = array_fill(0, $n, array_fill(0, $n, 0));
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n; $j++) {
            $dist[$i][$j] = abs($i - $j) * 10;
        }
    }
    $res = RouteOptimizer::optimize($dist);
    expect($res->isExact)->toBeFalse()
        ->and($res->cost)->toBe(240)
        ->and($res->lowerBound)->toBe(140)
        ->and($res->gapBps)->toBe(7142);
});

it('tests heuristic for n > 12 satisfying lower bound', function (): void {
    $n = 15;
    $seed = 20261031_11;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    $dist = array_fill(0, $n, array_fill(0, $n, 0));
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $val = $randomizer->getInt(10, 200);
            $dist[$i][$j] = $val;
            $dist[$j][$i] = $val;
        }
    }

    $result = RouteOptimizer::optimize($dist);

    expect($result->isExact)->toBeFalse()
        ->and($result->cost)->toBeGreaterThanOrEqual($result->lowerBound)
        ->and($result->gapBps)->toBeGreaterThanOrEqual(0);
});

it('benchmarks exact Held-Karp at n=12 in < 2s', function (): void {
    $n = 12;
    $seed = 20261031_12;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    $dist = array_fill(0, $n, array_fill(0, $n, 0));
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $val = $randomizer->getInt(10, 100);
            $dist[$i][$j] = $val;
            $dist[$j][$i] = $val;
        }
    }

    $start = microtime(true);
    $result = RouteOptimizer::optimize($dist);
    $duration = microtime(true) - $start;

    expect($result->isExact)->toBeTrue()
        ->and($duration)->toBeLessThan(2.0);
})->group('skala');

it('benchmarks heuristic at n=200 in < 2s', function (): void {
    $n = 200;
    $seed = 20261031_13;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    $dist = array_fill(0, $n, array_fill(0, $n, 0));
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $val = $randomizer->getInt(5, 500);
            $dist[$i][$j] = $val;
            $dist[$j][$i] = $val;
        }
    }

    $start = microtime(true);
    $result = RouteOptimizer::optimize($dist);
    $duration = microtime(true) - $start;

    expect($result->isExact)->toBeFalse()
        ->and($result->cost)->toBeGreaterThanOrEqual($result->lowerBound)
        ->and($duration)->toBeLessThan(2.0);
})->group('skala');
