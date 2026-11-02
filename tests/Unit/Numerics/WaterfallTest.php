<?php

declare(strict_types=1);

namespace Tests\Unit\Numerics;

use InvalidArgumentException;
use Modules\Shared\Domain\Numerics\Waterfall;
use Modules\Shared\Domain\Numerics\WaterfallVerifier;
use UnexpectedValueException;

test('waterfall exact vector: gross 100M, 1500 bps fee, 30M advance distributes 55M to H1, H2, H3 with exact conservation', function () {
    $rightsholders = [
        ['id' => 'H1', 'share_bps' => 5000],
        ['id' => 'H2', 'share_bps' => 3000],
        ['id' => 'H3', 'share_bps' => 2000],
    ];

    $result = Waterfall::calculate(
        grossRevenueIdr: 100000000,
        distributionFeeBps: 1500,
        unrecoupedAdvanceIdr: 30000000,
        rightsholders: $rightsholders,
    );

    expect($result['gross_revenue'])->toBe(100000000)
        ->and($result['distribution_fee'])->toBe(15000000)
        ->and($result['net_after_fee'])->toBe(85000000)
        ->and($result['recouped_advance'])->toBe(30000000)
        ->and($result['remaining_advance'])->toBe(0)
        ->and($result['distributable_pool'])->toBe(55000000)
        ->and($result['certificate']['rightsholders_count'])->toBe(3)
        ->and($result['certificate']['gross_revenue'])->toBe(100000000)
        ->and($result['certificate']['distribution_fee'])->toBe(15000000)
        ->and($result['certificate']['net_after_fee'])->toBe(85000000)
        ->and($result['certificate']['recouped_advance'])->toBe(30000000)
        ->and($result['certificate']['remaining_advance'])->toBe(0)
        ->and($result['certificate']['distributable_pool'])->toBe(55000000)
        ->and($result['certificate']['allocations'])->toHaveCount(3);

    $allocMap = array_column($result['allocations'], 'amount', 'id');
    expect($allocMap['H1'])->toBe(27500000)
        ->and($allocMap['H2'])->toBe(16500000)
        ->and($allocMap['H3'])->toBe(11000000);

    // Exact value conservation
    expect(15000000 + 30000000 + 27500000 + 16500000 + 11000000)->toBe(100000000);

    expect(WaterfallVerifier::verify($result, 30000000))->toBeTrue();
});

test('waterfall handles large unrecouped advance resulting in zero distributable pool', function () {
    $rightsholders = [
        ['id' => 'H1', 'share_bps' => 5000],
        ['id' => 'H2', 'share_bps' => 5000],
    ];

    $result = Waterfall::calculate(
        grossRevenueIdr: 100000000,
        distributionFeeBps: 1500,
        unrecoupedAdvanceIdr: 90000000,
        rightsholders: $rightsholders,
    );

    expect($result['gross_revenue'])->toBe(100000000)
        ->and($result['distribution_fee'])->toBe(15000000)
        ->and($result['net_after_fee'])->toBe(85000000)
        ->and($result['recouped_advance'])->toBe(85000000)
        ->and($result['remaining_advance'])->toBe(5000000)
        ->and($result['distributable_pool'])->toBe(0);

    foreach ($result['allocations'] as $alloc) {
        expect($alloc['amount'])->toBe(0);
    }

    expect(WaterfallVerifier::verify($result, 90000000))->toBeTrue();
});

test('waterfall uses Hamilton apportionment to guarantee zero-leak integer distribution', function () {
    // 10 IDR split among 3 equal partners (3334, 3333, 3333 bps)
    $rightsholders = [
        ['id' => 'A', 'share_bps' => 3334],
        ['id' => 'B', 'share_bps' => 3333],
        ['id' => 'C', 'share_bps' => 3333],
    ];

    $result = Waterfall::calculate(
        grossRevenueIdr: 10,
        distributionFeeBps: 0,
        unrecoupedAdvanceIdr: 0,
        rightsholders: $rightsholders,
    );

    $sumAlloc = array_sum(array_column($result['allocations'], 'amount'));
    expect($sumAlloc)->toBe(10);
    expect(WaterfallVerifier::verify($result, 0))->toBeTrue();
});

test('waterfall validates parameter boundaries', function () {
    $validRightsholders = [['id' => 'H', 'share_bps' => 10000]];

    expect(fn () => Waterfall::calculate(-1, 1000, 0, $validRightsholders))->toThrow(InvalidArgumentException::class);
    expect(fn () => Waterfall::calculate(1000, -1, 0, $validRightsholders))->toThrow(InvalidArgumentException::class);
    expect(fn () => Waterfall::calculate(1000, 10001, 0, $validRightsholders))->toThrow(InvalidArgumentException::class);
    expect(fn () => Waterfall::calculate(1000, 1000, -1, $validRightsholders))->toThrow(InvalidArgumentException::class);
    expect(fn () => Waterfall::calculate(1000, 1000, 0, []))->toThrow(InvalidArgumentException::class);
    expect(fn () => Waterfall::calculate(1000, 1000, 0, [['id' => 'H', 'share_bps' => 9000]]))->toThrow(InvalidArgumentException::class);
    expect(fn () => Waterfall::calculate(1000, 1000, 0, [['id' => 'H', 'share_bps' => -100]]))->toThrow(InvalidArgumentException::class);
    expect(fn () => Waterfall::calculate(1000, 1000, 0, [['invalid' => true]]))->toThrow(InvalidArgumentException::class);
});

test('waterfall verifier catches tampered results', function () {
    $rightsholders = [
        ['id' => 'H1', 'share_bps' => 5000],
        ['id' => 'H2', 'share_bps' => 5000],
    ];
    $base = Waterfall::calculate(100000, 1000, 20000, $rightsholders);

    // Missing keys
    expect(fn () => WaterfallVerifier::verify([], 20000))->toThrow(UnexpectedValueException::class);

    // Negative balance
    $c = $base;
    $c['distributable_pool'] = -100;
    expect(fn () => WaterfallVerifier::verify($c, 20000))->toThrow(UnexpectedValueException::class);

    // Net after fee mismatch
    $c = $base;
    $c['net_after_fee'] = 999999;
    expect(fn () => WaterfallVerifier::verify($c, 20000))->toThrow(UnexpectedValueException::class);

    // Pool mismatch
    $c = $base;
    $c['distributable_pool'] = 999999;
    expect(fn () => WaterfallVerifier::verify($c, 20000))->toThrow(UnexpectedValueException::class);

    // Remaining advance mismatch
    $c = $base;
    $c['remaining_advance'] = 999999;
    expect(fn () => WaterfallVerifier::verify($c, 20000))->toThrow(UnexpectedValueException::class);

    // Distributable pool > 0 when advance remains
    $c = $base;
    $c['remaining_advance'] = 5000;
    $c['distributable_pool'] = 5000;
    expect(fn () => WaterfallVerifier::verify($c, 25000))->toThrow(UnexpectedValueException::class);

    // Negative allocation
    $c = $base;
    $c['allocations'][0]['amount'] = -10;
    expect(fn () => WaterfallVerifier::verify($c, 20000))->toThrow(UnexpectedValueException::class);

    // Negative share bps in allocation
    $c = $base;
    $c['allocations'][0]['share_bps'] = -500;
    expect(fn () => WaterfallVerifier::verify($c, 20000))->toThrow(UnexpectedValueException::class);

    // Total shares != 10000
    $c = $base;
    $c['allocations'][0]['share_bps'] = 1000;
    expect(fn () => WaterfallVerifier::verify($c, 20000))->toThrow(UnexpectedValueException::class);

    // Sum allocations != pool
    $c = $base;
    $c['allocations'][0]['amount'] = 999999;
    expect(fn () => WaterfallVerifier::verify($c, 20000))->toThrow(UnexpectedValueException::class);

    // Total accounted != gross
    $c = $base;
    $c['gross_revenue'] = 999999999;
    expect(fn () => WaterfallVerifier::verify($c, 20000))->toThrow(UnexpectedValueException::class);
});

test('waterfall benchmark: 10.000 calculations run in < 0.5s', function () {
    $rightsholders = [
        ['id' => 'H1', 'share_bps' => 4000],
        ['id' => 'H2', 'share_bps' => 3500],
        ['id' => 'H3', 'share_bps' => 2500],
    ];

    $start = microtime(true);
    for ($i = 0; $i < 10000; $i++) {
        Waterfall::calculate(
            grossRevenueIdr: 1000000 + $i,
            distributionFeeBps: 1500,
            unrecoupedAdvanceIdr: 200000,
            rightsholders: $rightsholders,
        );
    }
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(0.5);
})->group('skala');
