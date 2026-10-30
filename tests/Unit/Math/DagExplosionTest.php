<?php

declare(strict_types=1);

namespace Tests\Unit\Math;

use Brick\Math\BigRational;
use InvalidArgumentException;
use Modules\Shared\Domain\Math\DagExplosion;
use Modules\Shared\Domain\Math\DagExplosionCertificate;
use Modules\Shared\Domain\Math\DagExplosionCertificateVerifier;
use Modules\Shared\Domain\Math\DagExplosionResult;
use Modules\Shared\Domain\Math\Exceptions\CycleDetectedException;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(DagExplosion::class, DagExplosionCertificateVerifier::class, DagExplosionResult::class);

it('rejects invalid inputs', function (): void {
    expect(fn () => DagExplosion::explode(['' => 1], []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DagExplosion::explode(['a' => -1], []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DagExplosion::explode(['a' => 1], ['a' => ['batch_yield' => 0, 'ingredients' => []]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DagExplosion::explode(['a' => 1], ['a' => ['batch_yield' => -2, 'ingredients' => []]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DagExplosion::explode(['a' => 1], ['a' => ['ingredients' => [['id' => '', 'quantity' => 10]]]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DagExplosion::explode(['a' => 1], ['a' => ['ingredients' => [['id' => 'b', 'quantity' => 0]]]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DagExplosion::explode(['a' => 1], ['a' => ['ingredients' => [['id' => 'b', 'quantity' => 10, 'yield_bps' => 0]]]]))->toThrow(InvalidArgumentException::class);
});

it('handles multiple demands sharing ingredients and empty ingredients recipe', function (): void {
    $recipes = [
        'dish1' => [
            'ingredients' => [
                ['id' => 'raw_shared', 'quantity' => 2],
            ],
        ],
        'dish2' => [
            'ingredients' => [
                ['id' => 'raw_shared', 'quantity' => 3],
            ],
        ],
        'empty_recipe' => [
            'ingredients' => [],
        ],
    ];

    $result = DagExplosion::explode(['dish1' => 2, 'dish2' => 4, 'empty_recipe' => 5], $recipes);
    expect($result['raw_shared'])->toBe(16)
        ->and($result['empty_recipe'])->toBe(5);
});

it('tests result ArrayAccess and Countable and Iterator', function (): void {
    $recipes = [
        'dish' => [
            'ingredients' => [
                ['id' => 'raw', 'quantity' => 5],
            ],
        ],
    ];
    $result = DagExplosion::explode(['dish' => 2], $recipes);

    expect(isset($result['raw']))->toBeTrue()
        ->and(isset($result['nonexistent']))->toBeFalse()
        ->and($result['raw'])->toBe(10)
        ->and($result['nonexistent'])->toBeNull()
        ->and(count($result))->toBe(1)
        ->and(iterator_to_array($result))->toBe(['raw' => 10])
        ->and($result->toArray())->toBe(['raw' => 10]);
});

it('handles diamond DAG multi-path accumulation and BigRational demands', function (): void {
    // Diamond DAG: A -> B and C, both B and C -> D
    $recipes = [
        'A' => [
            'batch_yield' => 1,
            'ingredients' => [
                ['id' => 'B', 'quantity' => 2],
                ['id' => 'C', 'quantity' => 3],
            ],
        ],
        'B' => [
            'batch_yield' => 1,
            'ingredients' => [
                ['id' => 'D', 'quantity' => 4],
            ],
        ],
        'C' => [
            'batch_yield' => 1,
            'ingredients' => [
                ['id' => 'D', 'quantity' => 5],
            ],
        ],
    ];

    // BigRational demand: 2 * 4 + 3 * 5 = 8 + 15 = 23 per unit of A.
    // For 1/2 unit of A: 23 / 2 = 11.5 -> ceil = 12
    $result = DagExplosion::explode(['A' => BigRational::of('1/2')], $recipes);
    expect($result['D'])->toBe(12)
        ->and($result->rawRationalTotals['D']->isEqualTo(BigRational::of('23/2')))->toBeTrue();
});

it('handles zero demand and self-loop cycle', function (): void {
    $recipes = [
        'dish' => [
            'ingredients' => [
                ['id' => 'raw', 'quantity' => 10],
            ],
        ],
    ];
    $zeroResult = DagExplosion::explode(['dish' => 0], $recipes);
    expect($zeroResult->count())->toBe(0);

    // Self cycle
    $selfLoop = [
        'X' => [
            'ingredients' => [
                ['id' => 'X', 'quantity' => 1],
            ],
        ],
    ];
    try {
        DagExplosion::explode(['X' => 1], $selfLoop);
        expect(false)->toBeTrue();
    } catch (CycleDetectedException $e) {
        expect($e->cyclePath)->toBe(['X', 'X']);
    }
});

it('tests verifier negative quantity, missing ceil, and key mismatch', function (): void {
    // Key mismatch
    $certMismatch = new DagExplosionCertificate(
        ['a' => BigRational::of('1')],
        ['b' => BigRational::of('1')],
        ['a' => 1]
    );
    expect(fn () => DagExplosionCertificateVerifier::verify($certMismatch))
        ->toThrow(InvalidCertificateException::class);

    // Negative quantity
    $certNeg = new DagExplosionCertificate(
        ['a' => BigRational::of('-1')],
        ['a' => BigRational::of('-1')],
        ['a' => -1]
    );
    expect(fn () => DagExplosionCertificateVerifier::verify($certNeg))
        ->toThrow(InvalidCertificateException::class);

    // Missing ceiled total
    $certMissing = new DagExplosionCertificate(
        ['a' => BigRational::of('1')],
        ['a' => BigRational::of('1')],
        []
    );
    expect(fn () => DagExplosionCertificateVerifier::verify($certMissing))
        ->toThrow(InvalidCertificateException::class);
});

it('detects cycles and returns cycle path in exception', function (): void {
    $cyclicRecipes = [
        'A' => [
            'ingredients' => [
                ['id' => 'B', 'quantity' => 1],
            ],
        ],
        'B' => [
            'ingredients' => [
                ['id' => 'C', 'quantity' => 1],
            ],
        ],
        'C' => [
            'ingredients' => [
                ['id' => 'A', 'quantity' => 1],
            ],
        ],
    ];

    try {
        DagExplosion::explode(['A' => 1], $cyclicRecipes);
        expect(false)->toBeTrue();
    } catch (CycleDetectedException $e) {
        expect($e->cyclePath)->toBe(['A', 'B', 'C', 'A']);
    }
});

it('matches required vector 1: 7 porsi Nasi Ayam', function (): void {
    $recipes = [
        'nasi_ayam' => [
            'batch_yield' => 1,
            'ingredients' => [
                ['id' => 'nasi', 'quantity' => 200],
                ['id' => 'ayam_mentah', 'quantity' => 120, 'yield_bps' => 8_000],
            ],
        ],
        'nasi' => [
            'batch_yield' => 250,
            'ingredients' => [
                ['id' => 'beras', 'quantity' => 100],
                ['id' => 'air', 'quantity' => 150],
            ],
        ],
    ];

    $result = DagExplosion::explode(['nasi_ayam' => 7], $recipes);

    expect($result['beras'])->toBe(560)
        ->and($result['air'])->toBe(840)
        ->and($result['ayam_mentah'])->toBe(1050)
        ->and($result->count())->toBe(3);
});

it('matches required vector 2: 3 porsi ayam 125g yield 8.000 bps', function (): void {
    $recipes = [
        'ayam_porsi' => [
            'batch_yield' => 1,
            'ingredients' => [
                ['id' => 'ayam_mentah', 'quantity' => 125, 'yield_bps' => 8_000],
            ],
        ],
    ];

    $result = DagExplosion::explode(['ayam_porsi' => 3], $recipes);

    // Exact rational is 375 * 10 / 8 = 468.75 -> ceil once on total = 469
    expect($result['ayam_mentah'])->toBe(469)
        ->and($result->rawRationalTotals['ayam_mentah']->isEqualTo(BigRational::of('1875/4')))->toBeTrue();
});

it('enforces certificate verification and rejects tampered certificates', function (): void {
    $recipes = [
        'menu' => [
            'ingredients' => [
                ['id' => 'item_1', 'quantity' => 10],
            ],
        ],
    ];
    $result = DagExplosion::explode(['menu' => 1], $recipes);

    DagExplosionCertificateVerifier::verify($result->certificate);

    // Discrepancy between Kahn and DFS
    $tamperedCert1 = new DagExplosionCertificate(
        ['item_1' => BigRational::of('10')],
        ['item_1' => BigRational::of('11')],
        ['item_1' => 10]
    );
    expect(fn () => DagExplosionCertificateVerifier::verify($tamperedCert1))->toThrow(InvalidCertificateException::class);

    // Discrepancy in ceiling
    $tamperedCert2 = new DagExplosionCertificate(
        ['item_1' => BigRational::of('10')],
        ['item_1' => BigRational::of('10')],
        ['item_1' => 9]
    );
    expect(fn () => DagExplosionCertificateVerifier::verify($tamperedCert2))->toThrow(InvalidCertificateException::class);
});

it('satisfies linearity property over random graph structures', function (): void {
    $recipes = [
        'dish' => [
            'batch_yield' => 2,
            'ingredients' => [
                ['id' => 'sauce', 'quantity' => 50],
                ['id' => 'meat', 'quantity' => 100, 'yield_bps' => 9000],
            ],
        ],
        'sauce' => [
            'batch_yield' => 100,
            'ingredients' => [
                ['id' => 'sugar', 'quantity' => 20],
                ['id' => 'water', 'quantity' => 80],
            ],
        ],
    ];

    $seed = 20261031_06;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 500; $i++) {
        $portions = $randomizer->getInt(1, 500);

        $res1 = DagExplosion::explode(['dish' => $portions], $recipes);
        $res2 = DagExplosion::explode(['dish' => $portions * 2], $recipes);

        // Before rounding, exact rationals are strictly linear
        foreach ($res1->rawRationalTotals as $rawId => $rational1) {
            $rational2 = $res2->rawRationalTotals[$rawId];
            expect($rational2->isEqualTo($rational1->multipliedBy(2)))->toBeTrue();
        }
    }
});

it('satisfies scale properties across 10.000 random cases', function (): void {
    $recipes = [
        'dish' => [
            'batch_yield' => 1,
            'ingredients' => [
                ['id' => 'ing_1', 'quantity' => 10],
                ['id' => 'ing_2', 'quantity' => 20, 'yield_bps' => 8500],
            ],
        ],
    ];

    $seed = 20261031_07;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 10_000; $i++) {
        $portions = $randomizer->getInt(1, 1000);
        $res = DagExplosion::explode(['dish' => $portions], $recipes);
        expect($res['ing_1'])->toBe($portions * 10)
            ->and($res['ing_2'])->toBeGreaterThan($portions * 20);
    }
})->group('skala');

it('benchmarks DAG BOM explosion scalability', function (): void {
    // Generate a multi-level BOM DAG with 100 nodes across 5 levels
    $recipes = [];
    for ($level = 0; $level < 4; $level++) {
        for ($i = 0; $i < 20; $i++) {
            $parentId = "level_{$level}_item_{$i}";
            $child1 = sprintf('level_%d_item_%d', $level + 1, $i);
            $child2 = sprintf('level_%d_item_%d', $level + 1, ($i + 1) % 20);
            $recipes[$parentId] = [
                'batch_yield' => 1,
                'ingredients' => [
                    ['id' => $child1, 'quantity' => 2],
                    ['id' => $child2, 'quantity' => 1],
                ],
            ];
        }
    }

    $start = microtime(true);
    for ($k = 0; $k < 100; $k++) {
        DagExplosion::explode(['level_0_item_0' => 5], $recipes);
    }
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(0.50);
})->group('skala');
