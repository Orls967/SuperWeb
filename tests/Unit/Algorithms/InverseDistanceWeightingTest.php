<?php

declare(strict_types=1);

use Modules\Mining\Domain\Algorithms\InverseDistanceWeightingCertificateVerifier;
use Modules\Mining\Domain\Algorithms\InverseDistanceWeightingInterpolator;
use Modules\Mining\Domain\Algorithms\InverseDistanceWeightingResult;

test('idw interpolation prompt vector and certificate validation', function () {
    $interpolator = new InverseDistanceWeightingInterpolator;

    // 4 sampel borehole mengelilingi target (koordinat x,y,z dalam mm, grade dalam ppm)
    $samples = [
        ['x' => 0, 'y' => 0, 'z' => 0, 'grade_ppm' => 12000],       // Ni 1.2%
        ['x' => 100000, 'y' => 0, 'z' => 0, 'grade_ppm' => 18000],   // Ni 1.8%
        ['x' => 0, 'y' => 100000, 'z' => 0, 'grade_ppm' => 15000],   // Ni 1.5%
        ['x' => 100000, 'y' => 100000, 'z' => 0, 'grade_ppm' => 21000], // Ni 2.1%
    ];

    // Target di tengah (50m, 50m, 0m)
    $target = ['x' => 50000, 'y' => 50000, 'z' => 0];

    $result = $interpolator->interpolate($target, $samples);

    // Karena simetris berjarak sama, rata-rata harus tepat (12k + 18k + 15k + 21k) / 4 = 16.500 ppm
    expect($result->interpolatedGradePpm)->toBe(16500);
    expect($result->sampleCount)->toBe(4);
    expect(count($result->weightsBreakdown))->toBe(4);

    // Verifier menerima sertifikat
    $verifier = new InverseDistanceWeightingCertificateVerifier;
    expect($verifier->verify($samples, $result))->toBeTrue();
});

test('tampered idw certificate throws exception', function () {
    $samples = [
        ['x' => 0, 'y' => 0, 'z' => 0, 'grade_ppm' => 12000],
        ['x' => 100000, 'y' => 0, 'z' => 0, 'grade_ppm' => 18000],
    ];
    $target = ['x' => 50000, 'y' => 0, 'z' => 0];

    $interpolator = new InverseDistanceWeightingInterpolator;
    $result = $interpolator->interpolate($target, $samples);

    $corrupted = new InverseDistanceWeightingResult(
        targetPoint: $result->targetPoint,
        interpolatedGradePpm: 99999, // Nilai dimanipulasi
        sampleCount: $result->sampleCount,
        weightsBreakdown: $result->weightsBreakdown,
        certificateHash: $result->certificateHash,
    );

    $verifier = new InverseDistanceWeightingCertificateVerifier;
    $verifier->verify($samples, $corrupted);
})->throws(InvalidArgumentException::class);
