<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Bidding\BidOptimizationCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Bidding\ExpectedProfitBidOptimizer;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

test('vektor prompt d84-2 expected profit bid optimizer', function () {
    $algo = new ExpectedProfitBidOptimizer;

    $c = 900;
    $history = [950, 980, 1000, 1000, 1050, 1100, 1200, 1250];
    $step = 5;

    // 1. Tanpa syarat P (minWinBps = 0)
    $resNoConstraint = $algo->optimize(c: $c, history: $history, step: $step, minWinBps: 0);
    expect($resNoConstraint->optimalPrice)->toBe(1195)
        ->and($resNoConstraint->expectedProfitFormatted)->toBe('73.750')
        ->and($resNoConstraint->certificate['is_valid'])->toBeTrue();

    // 2. Dengan P >= 30% (minWinBps = 3000)
    $resConstrained = $algo->optimize(c: $c, history: $history, step: $step, minWinBps: 3000);
    expect($resConstrained->optimalPrice)->toBe(1095)
        ->and($resConstrained->expectedProfitFormatted)->toBe('73.125')
        ->and($resConstrained->pWinFormatted)->toBe('0.3750')
        ->and($resConstrained->certificate['is_valid'])->toBeTrue();
});

test('sertifikat rusak expected profit bid optimizer ditolak dengan exception', function () {
    $algo = new ExpectedProfitBidOptimizer;
    $res = $algo->optimize(c: 900, history: [950, 980, 1000, 1000, 1050, 1100, 1200, 1250], step: 5, minWinBps: 3000);

    $corruptedCert = $res->certificate;
    $corruptedCert['optimal_price'] = 9999;

    $corruptedRes = $res->withCertificate($corruptedCert);

    $verifier = new BidOptimizationCertificateVerifier;
    $verifier->verify($corruptedRes);
})->throws(InvalidCertificateException::class);
