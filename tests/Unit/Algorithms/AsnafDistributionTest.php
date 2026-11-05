<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Islamic\AsnafDistribution;
use Modules\Shared\Domain\Algorithms\Islamic\AsnafDistributionCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Islamic\AsnafDistributionResult;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

test('vektor algoritma asnaf distribution zakat', function (): void {
    $algo = new AsnafDistribution();

    // Vektor prompt:
    // dana 1.000.003, bobot fakir 2500, miskin 2500, amil 1250, mualaf 500, riqab 250, gharimin 1000, fisabilillah 1000, ibnu sabil 1000
    // -> 250.001 / 250.001 / 125.001 / 50.000 / 25.000 / 100.000 / 100.000 / 100.000
    $weights = [
        AsnafDistribution::ASNAF_FAKIR => 2500,
        AsnafDistribution::ASNAF_MISKIN => 2500,
        AsnafDistribution::ASNAF_AMIL => 1250,
        AsnafDistribution::ASNAF_MUALAF => 500,
        AsnafDistribution::ASNAF_RIQAB => 250,
        AsnafDistribution::ASNAF_GHARIMIN => 1000,
        AsnafDistribution::ASNAF_FISABILILLAH => 1000,
        AsnafDistribution::ASNAF_IBNU_SABIL => 1000,
    ];

    $result = $algo->distribute(1_000_003, $weights);

    expect($result->allocationFor(AsnafDistribution::ASNAF_FAKIR))->toBe(250_001)
        ->and($result->allocationFor(AsnafDistribution::ASNAF_MISKIN))->toBe(250_001)
        ->and($result->allocationFor(AsnafDistribution::ASNAF_AMIL))->toBe(125_001)
        ->and($result->allocationFor(AsnafDistribution::ASNAF_MUALAF))->toBe(50_000)
        ->and($result->allocationFor(AsnafDistribution::ASNAF_RIQAB))->toBe(25_000)
        ->and($result->allocationFor(AsnafDistribution::ASNAF_GHARIMIN))->toBe(100_000)
        ->and($result->allocationFor(AsnafDistribution::ASNAF_FISABILILLAH))->toBe(100_000)
        ->and($result->allocationFor(AsnafDistribution::ASNAF_IBNU_SABIL))->toBe(100_000)
        ->and(array_sum($result->allocations))->toBe(1_000_003);
});

test('sertifikat rusak pada asnaf distribution memicu exception', function (): void {
    $corrupt = new AsnafDistributionResult(
        totalZakatFundIdr: 1_000_003,
        weightsBps: [
            AsnafDistribution::ASNAF_FAKIR => 2500,
            AsnafDistribution::ASNAF_MISKIN => 2500,
            AsnafDistribution::ASNAF_AMIL => 2000, // Rusak: amil > 1250 bps
            AsnafDistribution::ASNAF_MUALAF => 500,
            AsnafDistribution::ASNAF_RIQAB => 250,
            AsnafDistribution::ASNAF_GHARIMIN => 750,
            AsnafDistribution::ASNAF_FISABILILLAH => 750,
            AsnafDistribution::ASNAF_IBNU_SABIL => 750,
        ],
        allocations: [
            AsnafDistribution::ASNAF_FAKIR => 250_000,
        ]
    );

    (new AsnafDistributionCertificateVerifier())->verify($corrupt);
})->throws(InvalidCertificateException::class);
