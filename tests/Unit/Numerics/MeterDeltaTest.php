<?php

declare(strict_types=1);

use Modules\Shared\Domain\Numerics\MeterDelta;
use Modules\Shared\Domain\Numerics\MeterDeltaVerifier;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

test('meter delta exact vector: 99.950 to 30 with modulus 100.000 is 80', function () {
    $delta = MeterDelta::calculate(99_950, 30, 100_000);
    expect($delta)->toBe(80);
});

test('meter delta normal increment without rollover', function () {
    expect(MeterDelta::calculate(100, 250))->toBe(150);
    expect(MeterDelta::calculate(100, 100))->toBe(0);
});

test('meter delta rejects negative readings', function () {
    expect(fn () => MeterDelta::calculate(-1, 50))->toThrow(InvalidArgumentException::class);
    expect(fn () => MeterDelta::calculate(50, -1))->toThrow(InvalidArgumentException::class);
});

test('meter delta rejects invalid modulus', function () {
    expect(fn () => MeterDelta::calculate(50, 10, 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => MeterDelta::calculate(50, 10, -100))->toThrow(InvalidArgumentException::class);
});

test('meter delta rejects reading exceeding modulus', function () {
    expect(fn () => MeterDelta::calculate(100_000, 10, 100_000))->toThrow(InvalidArgumentException::class);
    expect(fn () => MeterDelta::calculate(10, 100_000, 100_000))->toThrow(InvalidArgumentException::class);
});

test('meter delta rejects backwards reading when modulus is null', function () {
    expect(fn () => MeterDelta::calculate(100, 50, null))->toThrow(InvalidArgumentException::class);
});

test('meter delta property test with randomizer', function () {
    $seed = 987654;
    $rng = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 100; $i++) {
        $mod = 100_000;
        $prev = $rng->getInt(0, $mod - 1);
        $curr = $rng->getInt(0, $mod - 1);

        $delta = MeterDelta::calculate($prev, $curr, $mod);
        expect($delta)->toBeGreaterThanOrEqual(0);
        expect($delta)->toBeLessThan($mod);
        expect(MeterDeltaVerifier::verify($prev, $curr, $mod, $delta))->toBeTrue();
    }
});

test('meter delta scale property test', function () {
    $seed = 987654;
    $rng = new Randomizer(new Xoshiro256StarStar($seed));

    for ($i = 0; $i < 1_000; $i++) {
        $mod = 1_000_000;
        $prev = $rng->getInt(0, $mod - 1);
        $curr = $rng->getInt(0, $mod - 1);

        $delta = MeterDelta::calculate($prev, $curr, $mod);
        expect($delta)->toBeGreaterThanOrEqual(0);
    }
})->group('skala');

test('meter delta verifier detects invalid delta', function () {
    expect(fn () => MeterDeltaVerifier::verify(100, 150, null, 40))->toThrow(UnexpectedValueException::class);
    expect(fn () => MeterDeltaVerifier::verify(100, 150, null, -10))->toThrow(UnexpectedValueException::class);
    expect(fn () => MeterDeltaVerifier::verify(90, 10, null, 20))->toThrow(UnexpectedValueException::class);
    expect(fn () => MeterDeltaVerifier::verify(90, 10, 100, 15))->toThrow(UnexpectedValueException::class);
});
