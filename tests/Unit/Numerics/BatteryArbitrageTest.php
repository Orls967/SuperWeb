<?php

declare(strict_types=1);

namespace Tests\Unit\Numerics;

use InvalidArgumentException;
use Modules\Shared\Domain\Numerics\BatteryArbitrage;
use Modules\Shared\Domain\Numerics\BatteryArbitrageVerifier;
use UnexpectedValueException;

test('battery arbitrage exact vector: 100 kWh, 50 kW, 10000 bps, prices [1k, 1k, 2k, 2k] gives 100k profit', function () {
    $prices = [1000, 1000, 2000, 2000];
    $result = BatteryArbitrage::optimize(
        capacityKwh: 100,
        maxKw: 50,
        prices: $prices,
        efficiencyBps: 10000,
        initialSocKwh: 0,
        minSocKwh: 0,
        maxSocKwh: 100,
        socStep: 1,
    );

    expect($result['total_profit'])->toBe(100000)
        ->and($result['initial_soc_kwh'])->toBe(0)
        ->and($result['final_soc_kwh'])->toBe(0)
        ->and($result['soc_trace'])->toBe([0, 50, 100, 50, 0])
        ->and($result['schedule'])->toHaveCount(4);

    expect($result['schedule'][0]['action'])->toBe('charge')
        ->and($result['schedule'][0]['kw'])->toBe(50)
        ->and($result['schedule'][0]['cash_flow'])->toBe(-50000);

    expect($result['schedule'][1]['action'])->toBe('charge')
        ->and($result['schedule'][1]['kw'])->toBe(50)
        ->and($result['schedule'][1]['cash_flow'])->toBe(-50000);

    expect($result['schedule'][2]['action'])->toBe('discharge')
        ->and($result['schedule'][2]['kw'])->toBe(50)
        ->and($result['schedule'][2]['cash_flow'])->toBe(100000);

    expect($result['schedule'][3]['action'])->toBe('discharge')
        ->and($result['schedule'][3]['kw'])->toBe(50)
        ->and($result['schedule'][3]['cash_flow'])->toBe(100000);

    expect(BatteryArbitrageVerifier::verify($result))->toBeTrue();
});

test('battery arbitrage exact vector: 100 kWh, 50 kW, 9000 bps gives 80k profit', function () {
    $prices = [1000, 1000, 2000, 2000];
    $result = BatteryArbitrage::optimize(
        capacityKwh: 100,
        maxKw: 50,
        prices: $prices,
        efficiencyBps: 9000,
        initialSocKwh: 0,
        minSocKwh: 0,
        maxSocKwh: 100,
        socStep: 1,
    );

    expect($result['total_profit'])->toBe(80000)
        ->and($result['initial_soc_kwh'])->toBe(0)
        ->and($result['final_soc_kwh'])->toBe(0)
        ->and($result['soc_trace'])->toBe([0, 50, 100, 50, 0]);

    // Discharges yield 50 * 2000 * 9000 / 10000 = 90,000 each
    expect($result['schedule'][2]['cash_flow'])->toBe(90000);
    expect($result['schedule'][3]['cash_flow'])->toBe(90000);

    expect(BatteryArbitrageVerifier::verify($result))->toBeTrue();
});

test('battery arbitrage stays idle when round-trip efficiency makes arbitrage unprofitable', function () {
    $prices = [1500, 1500, 1500, 1500];
    $result = BatteryArbitrage::optimize(
        capacityKwh: 100,
        maxKw: 50,
        prices: $prices,
        efficiencyBps: 9000,
        initialSocKwh: 0,
    );

    expect($result['total_profit'])->toBe(0)
        ->and($result['soc_trace'])->toBe([0, 0, 0, 0, 0]);

    foreach ($result['schedule'] as $step) {
        expect($step['action'])->toBe('idle')
            ->and($step['kw'])->toBe(0)
            ->and($step['cash_flow'])->toBe(0);
    }

    expect(BatteryArbitrageVerifier::verify($result))->toBeTrue();
});

test('battery arbitrage handles initial SoC > 0 discharging first then recharging', function () {
    $prices = [2500, 1000];
    $result = BatteryArbitrage::optimize(
        capacityKwh: 100,
        maxKw: 50,
        prices: $prices,
        efficiencyBps: 9000,
        initialSocKwh: 50,
    );

    // Hour 0: discharge 50 @ 2500 -> rev = 50 * 2500 * 0.9 = 112,500
    // Hour 1: charge 50 @ 1000 -> cost = -50,000
    // Profit = 62,500
    expect($result['total_profit'])->toBe(62500)
        ->and($result['soc_trace'])->toBe([50, 0, 50]);

    expect($result['schedule'][0]['action'])->toBe('discharge');
    expect($result['schedule'][1]['action'])->toBe('charge');

    expect(BatteryArbitrageVerifier::verify($result))->toBeTrue();
});

test('battery arbitrage validates parameter boundaries', function () {
    expect(fn () => BatteryArbitrage::optimize(0, 10, [1000]))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(-10, 10, [1000]))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 0, [1000]))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, -5, [1000]))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 10, [1000], efficiencyBps: 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 10, [1000], efficiencyBps: 10001))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 10, [1000], socStep: 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 10, []))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 10, [1000], minSocKwh: -1))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 10, [1000], minSocKwh: 101))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 10, [1000], minSocKwh: 50, maxSocKwh: 40))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 10, [1000], minSocKwh: 50, maxSocKwh: 150))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 10, [1000], initialSocKwh: -5))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 10, [1000], initialSocKwh: 150))->toThrow(InvalidArgumentException::class);
    expect(fn () => BatteryArbitrage::optimize(100, 10, [1000], initialSocKwh: 15, socStep: 10))->toThrow(InvalidArgumentException::class);
});

test('battery arbitrage verifier catches tampered results', function () {
    $base = BatteryArbitrage::optimize(
        capacityKwh: 100,
        maxKw: 50,
        prices: [1000, 1000, 2000, 2000],
        efficiencyBps: 10000,
        initialSocKwh: 0,
    );

    // Missing keys
    expect(fn () => BatteryArbitrageVerifier::verify([]))->toThrow(UnexpectedValueException::class);

    // Invalid capacity
    $c = $base;
    $c['capacity_kwh'] = 0;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Invalid max_kw
    $c = $base;
    $c['max_kw'] = -10;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Invalid efficiency_bps
    $c = $base;
    $c['efficiency_bps'] = 15000;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Broken cyclic condition
    $c = $base;
    $c['final_soc_kwh'] = 50;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Initial soc out of bounds
    $c = $base;
    $c['initial_soc_kwh'] = -1;
    $c['final_soc_kwh'] = -1;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Trace length mismatch
    $c = $base;
    $c['soc_trace'] = [0, 50];
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Trace start mismatch
    $c = $base;
    $c['soc_trace'][0] = 99;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Hour index mismatch
    $c = $base;
    $c['schedule'][0]['hour'] = 9;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Kw out of bounds
    $c = $base;
    $c['schedule'][0]['kw'] = 999;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // kWh != kW
    $c = $base;
    $c['schedule'][0]['kwh'] = 25;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Continuity broken
    $c = $base;
    $c['schedule'][1]['start_soc_kwh'] = 99;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Idle with non-zero kw
    $c = $base;
    $c['schedule'][0]['action'] = 'idle';
    $c['schedule'][0]['kw'] = 10;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Idle with non-zero cash flow
    $c = $base;
    $c['schedule'][0]['action'] = 'idle';
    $c['schedule'][0]['kw'] = 0;
    $c['schedule'][0]['cash_flow'] = 500;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Charge with non-positive kw
    $c = $base;
    $c['schedule'][0]['action'] = 'charge';
    $c['schedule'][0]['kw'] = 0;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Charge cash flow mismatch
    $c = $base;
    $c['schedule'][0]['cash_flow'] = 9999;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Discharge with non-positive kw
    $c = $base;
    $c['schedule'][2]['action'] = 'discharge';
    $c['schedule'][2]['kw'] = 0;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Discharge cash flow mismatch
    $c = $base;
    $c['schedule'][2]['cash_flow'] = 123;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Unknown action
    $c = $base;
    $c['schedule'][0]['action'] = 'teleport';
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // End SoC mismatch
    $c = $base;
    $c['schedule'][0]['end_soc_kwh'] = 99;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Trace end SoC mismatch
    $c = $base;
    $c['soc_trace'][1] = 99;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Final SoC mismatch
    $c = $base;
    $c['schedule'][3]['end_soc_kwh'] = 10;
    $c['schedule'][3]['cash_flow'] = intdiv(40 * 2000 * 10000 + 5000, 10000);
    $c['schedule'][3]['kw'] = 40;
    $c['schedule'][3]['kwh'] = 40;
    $c['soc_trace'][4] = 10;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);

    // Total profit mismatch
    $c = $base;
    $c['total_profit'] = 999999999;
    expect(fn () => BatteryArbitrageVerifier::verify($c))->toThrow(UnexpectedValueException::class);
});

test('battery arbitrage benchmark: 24h across 101 SoC steps runs under 1.0 second', function () {
    // 24 hours of fluctuating prices
    $prices = [
        800, 750, 700, 700, 750, 850,
        1200, 1500, 1800, 1900, 2000, 2100,
        2200, 2100, 1900, 1800, 2000, 2300,
        2500, 2400, 2100, 1600, 1200, 900,
    ];

    $start = microtime(true);
    $result = BatteryArbitrage::optimize(
        capacityKwh: 100,
        maxKw: 50,
        prices: $prices,
        efficiencyBps: 9000,
        initialSocKwh: 20,
        minSocKwh: 10,
        maxSocKwh: 90,
        socStep: 1, // 81 distinct reachable states
    );
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(1.0)
        ->and($result['total_profit'])->toBeGreaterThan(0)
        ->and($result['initial_soc_kwh'])->toBe(20)
        ->and($result['final_soc_kwh'])->toBe(20)
        ->and(BatteryArbitrageVerifier::verify($result))->toBeTrue();
})->group('skala');
