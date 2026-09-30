<?php

declare(strict_types=1);

use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

test('tracking number calculates correct luhn check digit for known vector', function () {
    // 7992739871 has Luhn check digit 3
    expect(TrackingNumber::computeLuhnCheckDigit('7992739871'))->toBe(3);

    // 0000000000 has Luhn check digit 0
    expect(TrackingNumber::computeLuhnCheckDigit('0000000000'))->toBe(0);

    // 1234567890
    // sum: 0*2 + 9 + 8*2(7) + 7 + 6*2(3) + 5 + 4*2(8) + 3 + 2*2(4) + 1 = 47
    // (10 - 7) % 10 = 3
    expect(TrackingNumber::computeLuhnCheckDigit('1234567890'))->toBe(3);
});

test('tracking number validates proper format and check digit', function () {
    $validTracking = 'SRX79927398713';
    expect(TrackingNumber::validate($validTracking))->toBeTrue();

    // Corrupted check digit
    expect(TrackingNumber::validate('SRX79927398714'))->toBeFalse();

    // Wrong prefix
    expect(TrackingNumber::validate('ABC79927398713'))->toBeFalse();

    // Too short
    expect(TrackingNumber::validate('SRX7992739871'))->toBeFalse();

    // Contains non-digit
    expect(TrackingNumber::validate('SRX7992739871A'))->toBeFalse();
});

test('tracking number generates valid 14-character code with luhn check digit', function () {
    for ($i = 0; $i < 20; $i++) {
        $tracking = TrackingNumber::generate();
        expect(strlen($tracking))->toBe(14)
            ->and(str_starts_with($tracking, 'SRX'))->toBeTrue()
            ->and(TrackingNumber::validate($tracking))->toBeTrue();
    }
});
