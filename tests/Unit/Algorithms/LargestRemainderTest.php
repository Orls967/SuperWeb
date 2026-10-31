<?php

declare(strict_types=1);

use Brick\Math\BigRational;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(LargestRemainder::class);

describe('LargestRemainder', function (): void {
    it('validates negative total and invalid items', function (): void {
        $lr = new LargestRemainder;

        expect(fn () => $lr->apportion(-1, [['key' => 'a', 'weight' => 1]]))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $lr->apportion(10, []))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $lr->apportion(10, [['key' => 'a', 'weight' => -5]]))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $lr->apportion(10, [['key' => 'a', 'weight' => 0]]))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $lr->apportion(10, [['weight' => 1]]))
            ->toThrow(InvalidArgumentException::class);
    });

    it('returns empty array when total is 0 and items empty', function (): void {
        $lr = new LargestRemainder;
        expect($lr->apportion(0, []))->toBe([]);
        expect($lr->apportion(0, [['key' => 'a', 'weight' => 10]]))->toBe(['a' => 0]);
    });

    it('handles mandatory test vectors exactly', function (): void {
        $lr = new LargestRemainder;

        // Vektor 1: 1000 split 1:1:1 -> 334 / 333 / 333 (tie-break key ascending: a gets the 1 extra)
        $result1 = $lr->apportion(1000, [
            ['key' => 'b', 'weight' => 1],
            ['key' => 'a', 'weight' => 1],
            ['key' => 'c', 'weight' => 1],
        ]);

        expect($result1)->toBe([
            'b' => 333,
            'a' => 334,
            'c' => 333,
        ]);
        expect(array_sum($result1))->toBe(1000);

        // Vektor 2: 5000 with weights 8:7 -> 2667 / 2333
        $result2 = $lr->apportion(5000, [
            ['key' => 'first', 'weight' => 8],
            ['key' => 'second', 'weight' => 7],
        ]);

        expect($result2)->toBe([
            'first' => 2667,
            'second' => 2333,
        ]);
        expect(array_sum($result2))->toBe(5000);
    });

    it('supports BigRational weights', function (): void {
        $lr = new LargestRemainder;

        $result = $lr->apportion(100, [
            ['key' => 'half', 'weight' => BigRational::of('1/2')],
            ['key' => 'quarter', 'weight' => BigRational::of('1/4')],
            ['key' => 'quarter2', 'weight' => BigRational::of('1/4')],
        ]);

        expect($result)->toBe([
            'half' => 50,
            'quarter' => 25,
            'quarter2' => 25,
        ]);
        expect(array_sum($result))->toBe(100);
    });

    it('property test: sum invariant, quota deviation < 1, and monotonicity', function (): void {
        $lr = new LargestRemainder;
        $seed = 987654321;
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

        for ($trial = 0; $trial < 500; $trial++) {
            $n = $randomizer->getInt(2, 10);
            $total = $randomizer->getInt(1, 100000);
            $items = [];
            $totalWeight = BigRational::zero();

            for ($i = 0; $i < $n; $i++) {
                $w = $randomizer->getInt(1, 100);
                $key = "k_{$i}";
                $items[] = ['key' => $key, 'weight' => $w];
                $totalWeight = $totalWeight->plus(BigRational::of($w));
            }

            $res = $lr->apportion($total, $items);

            // Invariant 1: sum exactly equals total
            expect(array_sum($res))->toBe($total);

            // Invariant 2: |allocated - exact quota| < 1
            foreach ($items as $item) {
                $exact = BigRational::of($total)
                    ->multipliedBy(BigRational::of($item['weight']))
                    ->dividedBy($totalWeight);
                $allocated = BigRational::of($res[$item['key']]);
                $diff = $allocated->minus($exact)->abs();
                expect($diff->isLessThan(BigRational::one()))->toBeTrue();
            }

            // Invariant 3: Monotonicity (if weight_a > weight_b, allocated_a >= allocated_b)
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if ($items[$i]['weight'] > $items[$j]['weight']) {
                        expect($res[$items[$i]['key']])->toBeGreaterThanOrEqual($res[$items[$j]['key']]);
                    }
                }
            }
        }
    });

    it('benchmarks 10000 items apportionment < 0.5s', function (): void {
        $lr = new LargestRemainder;
        $items = [];
        for ($i = 0; $i < 5000; $i++) {
            $items[] = ['key' => "item_{$i}", 'weight' => ($i % 10) + 1];
        }

        $start = microtime(true);
        $res = $lr->apportion(1000000, $items);
        $elapsed = microtime(true) - $start;

        expect(array_sum($res))->toBe(1000000)
            ->and($elapsed)->toBeLessThan(0.5);
    })->group('skala');
});
