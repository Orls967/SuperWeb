<?php

declare(strict_types=1);

namespace Tests\Unit\Numerics;

use InvalidArgumentException;
use Modules\Shared\Domain\Numerics\UsageRater;
use Modules\Shared\Domain\Numerics\UsageRaterVerifier;
use UnexpectedValueException;

test('usage rater exact vector: [1500, 1024, 1] bytes rated to 4096 bytes with 1 MB excess', function () {
    $sessions = [1500, 1024, 1];
    $result = UsageRater::rate(
        sessions: $sessions,
        monthlyQuotaBytes: 3000,
        basePackageCharge: 50000,
        excessRatePerMb: 5000,
    );

    expect($result['total_sessions'])->toBe(3)
        ->and($result['total_raw_bytes'])->toBe(2525)
        ->and($result['total_rated_bytes'])->toBe(4096)
        ->and($result['excess_bytes'])->toBe(1096)
        ->and($result['excess_mb'])->toBe(1)
        ->and($result['base_charge'])->toBe(50000)
        ->and($result['excess_charge'])->toBe(5000)
        ->and($result['total_charge'])->toBe(55000);

    expect($result['rated_sessions'][0]['rated_bytes'])->toBe(2048)
        ->and($result['rated_sessions'][1]['rated_bytes'])->toBe(1024)
        ->and($result['rated_sessions'][2]['rated_bytes'])->toBe(1024);

    expect(UsageRaterVerifier::verify($result, 3000, 50000, 5000))->toBeTrue();
});

test('usage rater handles within quota usage with 0 excess charge', function () {
    $sessions = [
        ['session_id' => 's-1', 'bytes' => 1000],
        ['session_id' => 's-2', 'bytes' => 500],
    ];

    $result = UsageRater::rate(
        sessions: $sessions,
        monthlyQuotaBytes: 100000,
        basePackageCharge: 100000,
        excessRatePerMb: 10000,
    );

    expect($result['total_rated_bytes'])->toBe(2048)
        ->and($result['excess_bytes'])->toBe(0)
        ->and($result['excess_mb'])->toBe(0)
        ->and($result['excess_charge'])->toBe(0)
        ->and($result['total_charge'])->toBe(100000);

    expect(UsageRaterVerifier::verify($result, 100000, 100000, 10000))->toBeTrue();
});

test('usage rater handles 0 byte session and exact MB excess boundary', function () {
    // 0 bytes rated to 0
    $zeroResult = UsageRater::rate([0], 1000, 10000, 5000);
    expect($zeroResult['total_rated_bytes'])->toBe(0);

    // Exact 1 MB excess (1,048,576 bytes)
    $exact1Mb = UsageRater::rate([1048576], 0, 0, 5000);
    expect($exact1Mb['excess_bytes'])->toBe(1048576)
        ->and($exact1Mb['excess_mb'])->toBe(1)
        ->and($exact1Mb['total_charge'])->toBe(5000);

    // 1 byte above 1 MB -> 2 MB
    $exact2Mb = UsageRater::rate([1048576, 1], 0, 0, 5000);
    expect($exact2Mb['excess_bytes'])->toBe(1048576 + 1024)
        ->and($exact2Mb['excess_mb'])->toBe(2)
        ->and($exact2Mb['total_charge'])->toBe(10000);
});

test('usage rater validates parameter boundaries', function () {
    expect(fn () => UsageRater::rate([], -1, 1000, 1000))->toThrow(InvalidArgumentException::class);
    expect(fn () => UsageRater::rate([], 1000, -1, 1000))->toThrow(InvalidArgumentException::class);
    expect(fn () => UsageRater::rate([], 1000, 1000, -1))->toThrow(InvalidArgumentException::class);
    expect(fn () => UsageRater::rate([-50], 1000, 1000, 1000))->toThrow(InvalidArgumentException::class);
    expect(fn () => UsageRater::rate(['invalid'], 1000, 1000, 1000))->toThrow(InvalidArgumentException::class);
});

test('usage rater verifier catches tampered result fields', function () {
    $base = UsageRater::rate([1500, 1024, 1], 3000, 50000, 5000);

    // Missing keys
    expect(fn () => UsageRaterVerifier::verify([], 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);

    // Quota mismatch
    $c = $base;
    $c['monthly_quota_bytes'] = 999;
    expect(fn () => UsageRaterVerifier::verify($c, 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);

    // Base charge mismatch
    $c = $base;
    $c['base_charge'] = 999;
    expect(fn () => UsageRaterVerifier::verify($c, 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);

    // Negative raw bytes
    $c = $base;
    $c['rated_sessions'][0]['raw_bytes'] = -10;
    expect(fn () => UsageRaterVerifier::verify($c, 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);

    // Tampered rated bytes in session
    $c = $base;
    $c['rated_sessions'][0]['rated_bytes'] = 1500;
    expect(fn () => UsageRaterVerifier::verify($c, 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);

    // Total sessions count mismatch
    $c = $base;
    $c['total_sessions'] = 99;
    expect(fn () => UsageRaterVerifier::verify($c, 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);

    // Total raw bytes mismatch
    $c = $base;
    $c['total_raw_bytes'] = 999999;
    expect(fn () => UsageRaterVerifier::verify($c, 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);

    // Total rated bytes mismatch
    $c = $base;
    $c['total_rated_bytes'] = 999999;
    expect(fn () => UsageRaterVerifier::verify($c, 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);

    // Excess bytes mismatch
    $c = $base;
    $c['excess_bytes'] = 999999;
    expect(fn () => UsageRaterVerifier::verify($c, 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);

    // Excess MB mismatch
    $c = $base;
    $c['excess_mb'] = 99;
    expect(fn () => UsageRaterVerifier::verify($c, 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);

    // Excess charge mismatch
    $c = $base;
    $c['excess_charge'] = 999999;
    expect(fn () => UsageRaterVerifier::verify($c, 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);

    // Total charge mismatch
    $c = $base;
    $c['total_charge'] = 999999;
    expect(fn () => UsageRaterVerifier::verify($c, 3000, 50000, 5000))->toThrow(UnexpectedValueException::class);
});

test('usage rater benchmark: 100.000 sessions rated under 0.5s', function () {
    $sessions = [];
    for ($i = 0; $i < 100000; $i++) {
        $sessions[] = ($i % 5000) + 1;
    }

    $start = microtime(true);
    $result = UsageRater::rate(
        sessions: $sessions,
        monthlyQuotaBytes: 50000000,
        basePackageCharge: 100000,
        excessRatePerMb: 5000,
    );
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(0.5)
        ->and($result['total_sessions'])->toBe(100000)
        ->and($result['total_charge'])->toBeGreaterThan(0)
        ->and(UsageRaterVerifier::verify($result, 50000000, 100000, 5000))->toBeTrue();
})->group('skala');
