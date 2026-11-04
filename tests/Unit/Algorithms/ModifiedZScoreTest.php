<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Stats\ModifiedZScore;
use Modules\Shared\Domain\Algorithms\Stats\ModifiedZScoreCertificateVerifier;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

test('vektor prompt d64-2 modified z-score deteksi anomali', function () {
    $algo = new ModifiedZScore;

    // Vektor 1: [100, 102, 98, 101, 99, 100, 103, 97, 100, 150]
    $data1 = [100, 102, 98, 101, 99, 100, 103, 97, 100, 150];

    $res150 = $algo->evaluate(150, $data1);
    expect($res150->median->toFloat())->toEqual(100.0)
        ->and($res150->mad->toFloat())->toEqual(1.5)
        ->and($res150->zFormatted)->toBe('22.483')
        ->and($res150->isAnomaly)->toBeTrue()
        ->and($res150->certificate['is_valid'])->toBeTrue();

    $res103 = $algo->evaluate(103, $data1);
    expect($res103->zFormatted)->toBe('1.349')
        ->and($res103->isAnomaly)->toBeFalse();

    $res97 = $algo->evaluate(97, $data1);
    expect($res97->zFormatted)->toBe('-1.349')
        ->and($res97->isAnomaly)->toBeFalse();

    // Vektor 2: [100, 100, 100, 100, 130] (MAD = 0, MeanAD = 6)
    $data2 = [100, 100, 100, 100, 130];
    $res130 = $algo->evaluate(130, $data2);
    expect($res130->mad->toFloat())->toEqual(0.0)
        ->and($res130->meanAd?->toFloat())->toEqual(6.0)
        ->and($res130->zFormatted)->toBe('3.989')
        ->and($res130->isAnomaly)->toBeTrue()
        ->and($res130->certificate['is_valid'])->toBeTrue();
});

test('sertifikat rusak modified z-score ditolak dengan exception', function () {
    $algo = new ModifiedZScore;
    $res = $algo->evaluate(150, [100, 102, 98, 101, 99, 100, 103, 97, 100, 150]);

    $corruptedCertificate = $res->certificate;
    $corruptedCertificate['computed_z'] = '0.000'; // Rusak sertifikat

    $corruptedResult = $res->withCertificate($corruptedCertificate);

    $verifier = new ModifiedZScoreCertificateVerifier;
    $verifier->verify($corruptedResult);
})->throws(InvalidCertificateException::class);

