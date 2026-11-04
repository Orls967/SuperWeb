<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Revenue\CostToCostCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Revenue\CostToCostRecognition;
use Modules\Shared\Domain\Algorithms\Revenue\CostToCostResult;

test('vektor prompt d25 3 cost to cost recognition', function () {
    $contract = 1000000000;

    // P1 biaya 200 jt dari estimasi 800 jt -> 250.000.000
    $p1 = CostToCostRecognition::calculate($contract, 200000000, 800000000, 0);
    expect($p1->cumulativeRecognizedIdr)->toBe(250000000)
        ->and($p1->periodRevenueIdr)->toBe(250000000);

    // P2 biaya kumulatif 500 jt, estimasi direvisi 850 jt -> kumulatif 588.235.294 (periode 338.235.294)
    $p2 = CostToCostRecognition::calculate($contract, 500000000, 850000000, $p1->cumulativeRecognizedIdr);
    expect($p2->cumulativeRecognizedIdr)->toBe(588235294)
        ->and($p2->periodRevenueIdr)->toBe(338235294);

    // P3 selesai di 850 jt -> kumulatif 1.000.000.000 (periode 411.764.706)
    $p3 = CostToCostRecognition::calculate($contract, 850000000, 850000000, $p2->cumulativeRecognizedIdr);
    expect($p3->cumulativeRecognizedIdr)->toBe(1000000000)
        ->and($p3->periodRevenueIdr)->toBe(411764706);
});

test('sertifikat rusak melempar exception', function () {
    $corrupted = new CostToCostResult(
        contractAmountIdr: 1000000000,
        cumulativeCostIdr: 200000000,
        totalEstimatedCostIdr: 800000000,
        previouslyRecognizedIdr: 0,
        cumulativeRecognizedIdr: 250000000,
        periodRevenueIdr: 250000000,
        certificateSha256: 'corrupted-fake-sha256',
    );

    expect(fn () => CostToCostCertificateVerifier::verify($corrupted))
        ->toThrow(RuntimeException::class, 'Sertifikat CostToCost tidak valid');
});
