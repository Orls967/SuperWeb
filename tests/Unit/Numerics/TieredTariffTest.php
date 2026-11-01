<?php

declare(strict_types=1);

use Modules\Shared\Domain\Numerics\TieredTariff;
use Modules\Shared\Domain\Numerics\TieredTariffVerifier;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

test('tiered tariff exact vector: 350 units produces 500.000', function () {
    $tiers = [
        ['up_to' => 100, 'rate' => 1_000],  // 100 * 1000 = 100.000
        ['up_to' => 300, 'rate' => 1_500],  // 200 * 1500 = 300.000
        ['up_to' => null, 'rate' => 2_000], // 50 * 2000 = 100.000
    ];

    $result = TieredTariff::calculate(350, $tiers);

    expect($result['total_cost'])->toBe(500_000);
    expect($result['breakdown'])->toHaveCount(3);
    expect($result['breakdown'][0]['units'])->toBe(100);
    expect($result['breakdown'][1]['units'])->toBe(200);
    expect($result['breakdown'][2]['units'])->toBe(50);
});

test('tiered tariff zero units produces zero cost', function () {
    $tiers = [
        ['up_to' => 100, 'rate' => 1_000],
        ['up_to' => null, 'rate' => 2_000],
    ];

    $result = TieredTariff::calculate(0, $tiers);
    expect($result['total_cost'])->toBe(0);
});

test('tiered tariff rejects negative units', function () {
    $tiers = [['up_to' => null, 'rate' => 100]];
    expect(fn () => TieredTariff::calculate(-5, $tiers))->toThrow(InvalidArgumentException::class);
});

test('tiered tariff rejects empty tiers', function () {
    expect(fn () => TieredTariff::calculate(10, []))->toThrow(InvalidArgumentException::class);
});

test('tiered tariff rejects non-increasing thresholds or negative rates', function () {
    expect(fn () => TieredTariff::calculate(10, [
        ['up_to' => 100, 'rate' => -5],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => TieredTariff::calculate(10, [
        ['up_to' => 100, 'rate' => 10],
        ['up_to' => 50, 'rate' => 20],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => TieredTariff::calculate(10, [
        ['up_to' => null, 'rate' => 10],
        ['up_to' => 100, 'rate' => 20],
    ]))->toThrow(InvalidArgumentException::class);
});

test('tiered tariff property tests with randomizer', function () {
    $seed = 554433;
    $rng = new Randomizer(new Xoshiro256StarStar($seed));

    $tiers = [
        ['up_to' => 50, 'rate' => 500],
        ['up_to' => 150, 'rate' => 1_000],
        ['up_to' => 500, 'rate' => 1_500],
        ['up_to' => null, 'rate' => 2_500],
    ];

    for ($i = 0; $i < 50; $i++) {
        $units = $rng->getInt(0, 10_000);
        $res = TieredTariff::calculate($units, $tiers);

        expect($res['total_cost'])->toBeGreaterThanOrEqual(0);
        expect(TieredTariffVerifier::verify($units, $tiers, $res))->toBeTrue();
    }
});

test('tiered tariff verifier catches corruption', function () {
    $tiers = [
        ['up_to' => 100, 'rate' => 1_000],
        ['up_to' => null, 'rate' => 2_000],
    ];

    $corrupt = [
        'total_cost' => 100_001,
        'breakdown' => [
            ['tier_index' => 0, 'units' => 100, 'rate' => 1_000, 'cost' => 100_000],
            ['tier_index' => 1, 'units' => 0, 'rate' => 2_000, 'cost' => 0],
        ],
    ];

    expect(fn () => TieredTariffVerifier::verify(100, $tiers, $corrupt))->toThrow(UnexpectedValueException::class);
});
