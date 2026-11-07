<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Geo\OrdinaryKriging3D;
use Modules\Shared\Domain\Algorithms\Geo\OrdinaryKriging3DCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Geo\OrdinaryKriging3DResult;

test('ordinary kriging 3d geostatistical interpolation and verification', function () {
    $kriging = new OrdinaryKriging3D;

    $samples = [
        ['x' => 0, 'y' => 0, 'z' => 0, 'grade_ppm' => 14000],
        ['x' => 100000, 'y' => 0, 'z' => 0, 'grade_ppm' => 18000],
        ['x' => 0, 'y' => 100000, 'z' => 0, 'grade_ppm' => 16000],
        ['x' => 100000, 'y' => 100000, 'z' => 0, 'grade_ppm' => 20000],
    ];

    $target = ['x' => 50000, 'y' => 50000, 'z' => 0];

    $result = $kriging->estimate($target, $samples);

    // Pada titik simetris di tengah 4 titik bujur sangkar, estimasi tepat rata-rata (17000 ppm)
    expect($result->estimatedGradePpm)->toBe(17000);
    expect($result->krigingVarianceBps)->toBeGreaterThanOrEqual(0);

    // Verifier menerima
    $verifier = new OrdinaryKriging3DCertificateVerifier;
    expect($verifier->verify($samples, $result))->toBeTrue();
});

test('tampered ordinary kriging certificate throws exception', function () {
    $samples = [
        ['x' => 0, 'y' => 0, 'z' => 0, 'grade_ppm' => 14000],
        ['x' => 100000, 'y' => 0, 'z' => 0, 'grade_ppm' => 18000],
    ];
    $target = ['x' => 50000, 'y' => 0, 'z' => 0];

    $kriging = new OrdinaryKriging3D;
    $result = $kriging->estimate($target, $samples);

    $corrupted = new OrdinaryKriging3DResult(
        targetPoint: $result->targetPoint,
        estimatedGradePpm: 99999,
        krigingVarianceBps: -500, // Varians negatif tidak valid
        weightsBreakdown: $result->weightsBreakdown,
        lagrangeMultiplierBps: $result->lagrangeMultiplierBps,
        certificateHash: $result->certificateHash,
    );

    $verifier = new OrdinaryKriging3DCertificateVerifier;
    $verifier->verify($samples, $corrupted);
})->throws(InvalidArgumentException::class);
