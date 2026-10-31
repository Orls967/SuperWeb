<?php

declare(strict_types=1);

namespace Tests\Unit\Math;

use Brick\Math\BigRational;
use InvalidArgumentException;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use Modules\Shared\Domain\Math\HamiltonApportionment;
use Modules\Shared\Domain\Math\HamiltonApportionmentResult;
use Modules\Shared\Domain\Math\HamiltonCertificate;
use Modules\Shared\Domain\Math\HamiltonCertificateVerifier;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(
    HamiltonApportionment::class,
    HamiltonCertificateVerifier::class,
    HamiltonApportionmentResult::class
);

it('rejects invalid inputs', function (): void {
    expect(fn () => HamiltonApportionment::apportion(-1, [['key' => 'a', 'weight' => 1]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => HamiltonApportionment::apportion(100, []))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => HamiltonApportionment::apportion(100, [['key' => '', 'weight' => 1]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => HamiltonApportionment::apportion(100, [['key' => 'a', 'weight' => 1], ['key' => 'a', 'weight' => 2]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => HamiltonApportionment::apportion(100, [['key' => 'a', 'weight' => 0]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => HamiltonApportionment::apportion(100, [['key' => 'a', 'weight' => 1, 'cap' => -5]]))
        ->toThrow(InvalidArgumentException::class);
});

it('handles zero total gracefully', function (): void {
    $res0 = HamiltonApportionment::apportion(0, []);
    expect($res0->allocations)->toBe([])
        ->and($res0->unallocated)->toBe(0);

    $res1 = HamiltonApportionment::apportion(0, [['key' => 'a', 'weight' => 10]]);
    expect($res1['a'])->toBe(0)
        ->and($res1->unallocated)->toBe(0);
});

it('matches required vector 1: 1.000.000 bobot 1:1:1', function (): void {
    $result = HamiltonApportionment::apportion(1_000_000, [
        ['key' => '1', 'weight' => 1],
        ['key' => '2', 'weight' => 1],
        ['key' => '3', 'weight' => 1],
    ]);

    expect($result['1'])->toBe(333_334)
        ->and($result['2'])->toBe(333_333)
        ->and($result['3'])->toBe(333_333)
        ->and(array_sum($result->toArray()))->toBe(1_000_000);
});

it('matches required vector 2: 10.000.000 bobot 333:333:334', function (): void {
    $result = HamiltonApportionment::apportion(10_000_000, [
        ['key' => 'a', 'weight' => 333],
        ['key' => 'b', 'weight' => 333],
        ['key' => 'c', 'weight' => 334],
    ]);

    expect($result['a'])->toBe(3_330_000)
        ->and($result['b'])->toBe(3_330_000)
        ->and($result['c'])->toBe(3_340_000)
        ->and(array_sum($result->toArray()))->toBe(10_000_000);
});

it('matches required vector 3: suplai 1.000, pesanan 600/300/300', function (): void {
    $result = HamiltonApportionment::apportion(1_000, [
        ['key' => 'investor_1', 'weight' => 600, 'cap' => 600],
        ['key' => 'investor_2', 'weight' => 300, 'cap' => 300],
        ['key' => 'investor_3', 'weight' => 300, 'cap' => 300],
    ]);

    expect($result['investor_1'])->toBe(500)
        ->and($result['investor_2'])->toBe(250)
        ->and($result['investor_3'])->toBe(250)
        ->and(array_sum($result->toArray()))->toBe(1_000);
});

it('matches required vector 4: suplai 1.000, pesanan 700/700/700', function (): void {
    $result = HamiltonApportionment::apportion(1_000, [
        ['key' => 'inv_1', 'weight' => 700, 'cap' => 700],
        ['key' => 'inv_2', 'weight' => 700, 'cap' => 700],
        ['key' => 'inv_3', 'weight' => 700, 'cap' => 700],
    ]);

    expect($result['inv_1'])->toBe(334)
        ->and($result['inv_2'])->toBe(333)
        ->and($result['inv_3'])->toBe(333)
        ->and(array_sum($result->toArray()))->toBe(1_000);
});

it('handles cap saturation with unallocated remainder', function (): void {
    $result = HamiltonApportionment::apportion(1_500, [
        ['key' => 'a', 'weight' => 600, 'cap' => 600],
        ['key' => 'b', 'weight' => 300, 'cap' => 300],
        ['key' => 'c', 'weight' => 300, 'cap' => 300],
    ]);

    expect($result['a'])->toBe(600)
        ->and($result['b'])->toBe(300)
        ->and($result['c'])->toBe(300)
        ->and($result->unallocated)->toBe(300)
        ->and(array_sum($result->toArray()) + $result->unallocated)->toBe(1_500);
});

it('supports BigRational weights', function (): void {
    $result = HamiltonApportionment::apportion(100, [
        ['key' => 'x', 'weight' => BigRational::of('1/3')],
        ['key' => 'y', 'weight' => BigRational::of('2/3')],
    ]);

    expect($result['x'])->toBe(33)
        ->and($result['y'])->toBe(67)
        ->and(array_sum($result->toArray()))->toBe(100);
});

it('enforces certificate verification and rejects tampered certificates', function (): void {
    $result = HamiltonApportionment::apportion(1_000, [
        ['key' => 'a', 'weight' => 500],
        ['key' => 'b', 'weight' => 500],
    ]);

    // Valid certificate passes
    HamiltonCertificateVerifier::verify($result->certificate);

    // Tampered certificate with invalid sum throws
    $tamperedCert1 = new HamiltonCertificate(1_000, 0, $result->certificate->items, $result->certificate->quotas, [
        'a' => 500,
        'b' => 499,
    ]);
    expect(fn () => HamiltonCertificateVerifier::verify($tamperedCert1))->toThrow(InvalidCertificateException::class);

    // Tampered certificate with quota violation throws
    $tamperedCert2 = new HamiltonCertificate(1_000, 0, $result->certificate->items, $result->certificate->quotas, [
        'a' => 400,
        'b' => 600,
    ]);
    expect(fn () => HamiltonCertificateVerifier::verify($tamperedCert2))->toThrow(InvalidCertificateException::class);
});

it('satisfies properties across 500 random cases (default suite)', function (): void {
    $seed = 20261031;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 500; $i++) {
        $itemCount = $randomizer->getInt(1, 15);
        $total = $randomizer->getInt(1, 50_000);

        $items = [];
        for ($j = 0; $j < $itemCount; $j++) {
            $items[] = [
                'key' => "key_{$j}",
                'weight' => $randomizer->getInt(1, 1_000),
            ];
        }

        $result = HamiltonApportionment::apportion($total, $items);

        // Conservation property
        expect(array_sum($result->toArray()) + $result->unallocated)->toBe($total);

        // Order invariance: reverse items and check results match
        $reversed = array_reverse($items);
        $resultReversed = HamiltonApportionment::apportion($total, $reversed);
        expect($resultReversed->toArray())->toBe($result->toArray());
    }
});

it('satisfies scale properties across 10.000 random cases', function (): void {
    $seed = 20261031_01;
    $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 10_000; $i++) {
        $itemCount = $randomizer->getInt(1, 10);
        $total = $randomizer->getInt(1, 20_000);

        $items = [];
        for ($j = 0; $j < $itemCount; $j++) {
            $items[] = [
                'key' => "k_{$j}",
                'weight' => $randomizer->getInt(1, 500),
            ];
        }

        $result = HamiltonApportionment::apportion($total, $items);
        expect(array_sum($result->toArray()))->toBe($total);
    }
})->group('skala');

it('benchmarks Hamilton apportionment scalability', function (): void {
    $items = [];
    for ($i = 0; $i < 1_000; $i++) {
        $items[] = [
            'key' => sprintf('user_%04d', $i),
            'weight' => ($i % 50) + 1,
            'cap' => 1_000,
        ];
    }

    $start = microtime(true);
    $result = HamiltonApportionment::apportion(500_000, $items);
    $duration = microtime(true) - $start;

    expect(count($result->allocations))->toBe(1_000)
        ->and(array_sum($result->toArray()))->toBe(500_000)
        ->and($duration)->toBeLessThan(0.10); // Under 100ms
});
