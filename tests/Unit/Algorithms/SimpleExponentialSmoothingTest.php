<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Forecast\SimpleExponentialSmoothing;
use Modules\Shared\Domain\Algorithms\Forecast\SimpleExponentialSmoothingCertificateVerifier;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

test('vektor prompt d64-3 simple exponential smoothing rekomendasi stok', function () {
    $algo = new SimpleExponentialSmoothing;

    // Vektor: [100, 120, 110, 130], alpha 3.000 bps (0.3), faktor libur 12.500 bps (1.25)
    $demands = [100, 120, 110, 130];
    $alphaBps = 3000;
    $holidayFactorBps = 12500;

    $result = $algo->forecastWithHolidayAdjustment($demands, $alphaBps, $holidayFactorBps);

    expect($result->levelFormatted)->toBe('114.04')
        ->and($result->adjustedLevelFormatted)->toBe('142.55')
        ->and($result->suggestedUnitsPerDay)->toBe(143)
        ->and($result->certificate['is_valid'])->toBeTrue();
});

test('sertifikat rusak simple exponential smoothing ditolak dengan exception', function () {
    $algo = new SimpleExponentialSmoothing;
    $result = $algo->forecastWithHolidayAdjustment([100, 120, 110, 130], 3000, 12500);

    $corruptedCertificate = $result->certificate;
    $corruptedCertificate['suggested_units'] = 999; // Rusak sertifikat

    $corruptedResult = $result->withCertificate($corruptedCertificate);

    $verifier = new SimpleExponentialSmoothingCertificateVerifier;
    $verifier->verify($corruptedResult);
})->throws(InvalidCertificateException::class);

