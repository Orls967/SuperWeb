<?php

declare(strict_types=1);

namespace Tests\Unit\Math;

use InvalidArgumentException;
use Modules\Shared\Domain\Math\MeterDelta;
use Modules\Shared\Domain\Math\MeterReading;

covers(MeterDelta::class);

test('it rejects invalid inputs', function () {
    expect(fn () => new MeterReading(-1, 100))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new MeterReading(100, -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => MeterDelta::calculate([], -5))->toThrow(InvalidArgumentException::class)
        ->and(fn () => MeterDelta::calculate([], 0))->toThrow(InvalidArgumentException::class);

    // Reading exceeds modulus
    $r = new MeterReading(100, 100_000);
    expect(fn () => MeterDelta::calculate([$r], 100_000))->toThrow(InvalidArgumentException::class);

    // Invalid item type
    expect(fn () => MeterDelta::calculate(['invalid']))->toThrow(InvalidArgumentException::class);
});

test('it handles empty or single reading', function () {
    $resEmpty = MeterDelta::calculate([]);
    expect($resEmpty->totalConsumption)->toBe(0)
        ->and($resEmpty->intervals)->toBe([])
        ->and($resEmpty->hasAnomalies)->toBeFalse();

    $resSingle = MeterDelta::calculate([new MeterReading(1000, 50)]);
    expect($resSingle->totalConsumption)->toBe(0)
        ->and($resSingle->intervals)->toBe([])
        ->and($resSingle->hasAnomalies)->toBeFalse();
});

test('it matches required vector: register maks 99.999 (modulus 100.000): 99.950 -> 30 = 80', function () {
    $readings = [
        new MeterReading(timestamp: 1000, register: 99_950),
        new MeterReading(timestamp: 2000, register: 30),
    ];

    $res = MeterDelta::calculate($readings, 100_000);

    expect($res->totalConsumption)->toBe(80)
        ->and($res->rolloverCount)->toBe(1)
        ->and($res->resetCount)->toBe(0)
        ->and($res->hasAnomalies)->toBeFalse()
        ->and(count($res->intervals))->toBe(1);

    $interval = $res->intervals[0];
    expect($interval->consumption)->toBe(80)
        ->and($interval->isRollover)->toBeTrue()
        ->and($interval->isReset)->toBeFalse()
        ->and($interval->startRegister)->toBe(99_950)
        ->and($interval->endRegister)->toBe(30);
});

test('it ignores duplicate readings with identical timestamp', function () {
    $readings = [
        ['timestamp' => 1000, 'register' => 100],
        ['timestamp' => 1000, 'register' => 105], // Duplicate timestamp ignored
        ['timestamp' => 2000, 'register' => 150],
    ];

    $res = MeterDelta::calculate($readings);

    expect(count($res->intervals))->toBe(1)
        ->and($res->totalConsumption)->toBe(50)
        ->and($res->intervals[0]->startRegister)->toBe(100)
        ->and($res->intervals[0]->endRegister)->toBe(150);
});

test('it handles intentional reset events', function () {
    $readings = [
        new MeterReading(timestamp: 1000, register: 500),
        new MeterReading(timestamp: 2000, register: 100, isReset: true), // Meter replaced, starts at 0 -> reads 100
        new MeterReading(timestamp: 3000, register: 150),
    ];

    $res = MeterDelta::calculate($readings);

    expect($res->resetCount)->toBe(1)
        ->and($res->rolloverCount)->toBe(0)
        ->and($res->hasAnomalies)->toBeFalse()
        ->and($res->totalConsumption)->toBe(150); // 100 + 50
});

test('it detects negative drops without reset as anomalies when no modulus is provided', function () {
    $readings = [
        new MeterReading(timestamp: 1000, register: 500),
        new MeterReading(timestamp: 2000, register: 400), // Drop from 500 to 400 without reset
    ];

    $res = MeterDelta::calculate($readings, null);

    expect($res->hasAnomalies)->toBeTrue()
        ->and(count($res->anomalies))->toBe(1)
        ->and($res->totalConsumption)->toBe(0)
        ->and($res->intervals[0]->isAnomaly)->toBeTrue();
});

test('it verifies the rollover property: sum(consumption) = reg_akhir - reg_awal + modulus * jumlah_rollover', function () {
    mt_srand(7777);

    $modulus = 100_000;

    for ($iter = 0; $iter < 100; $iter++) {
        $numReadings = mt_rand(5, 20);
        $readings = [];
        $currentTimestamp = 1000;
        $currentRegister = mt_rand(0, $modulus - 1);

        $readings[] = new MeterReading($currentTimestamp, $currentRegister);

        for ($i = 1; $i < $numReadings; $i++) {
            $currentTimestamp += mt_rand(60, 3600);
            $delta = mt_rand(10, 2000);
            $currentRegister = ($currentRegister + $delta) % $modulus;
            $readings[] = new MeterReading($currentTimestamp, $currentRegister);
        }

        $res = MeterDelta::calculate($readings, $modulus);

        $firstReg = $readings[0]->register;
        $lastReg = end($readings)->register;

        $expectedSum = $lastReg - $firstReg + ($modulus * $res->rolloverCount);

        expect($res->totalConsumption)->toBe($expectedSum)
            ->and($res->hasAnomalies)->toBeFalse();
    }
});

test('it scales efficiently for 10000 readings', function () {
    $readings = [];
    $mod = 100_000;
    $reg = 0;
    for ($i = 0; $i < 10_000; $i++) {
        $reg = ($reg + 25) % $mod;
        $readings[] = new MeterReading($i * 60, $reg);
    }

    $res = MeterDelta::calculate($readings, $mod);
    expect($res->intervals)->toHaveCount(9_999)
        ->and($res->totalConsumption)->toBe(25 * 9_999);
})->group('skala');
