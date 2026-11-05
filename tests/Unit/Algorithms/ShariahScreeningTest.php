<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Islamic\ShariahScreening;
use Modules\Shared\Domain\Algorithms\Islamic\ShariahScreeningCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Islamic\ShariahScreeningResult;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

test('vektor algoritma shariah screening daft dan purifikasi dividen', function (): void {
    $algo = new ShariahScreening();

    // Vektor prompt:
    // utang 400 / aset 1.000 = 4000 bps lolos;
    $res1 = $algo->screen(
        interestBearingDebtIdr: 400,
        totalAssetsIdr: 1000,
        nonHalalRevenueIdr: 50,
        totalRevenueIdr: 1000
    );
    expect($res1->debtRatioBps)->toBe(4000)
        ->and($res1->debtCompliant)->toBeTrue()
        ->and($res1->isCompliant)->toBeTrue();

    // non-halal 120 / pendapatan 1.000 = 1200 bps GAGAL;
    $res2 = $algo->screen(
        interestBearingDebtIdr: 200,
        totalAssetsIdr: 1000,
        nonHalalRevenueIdr: 120,
        totalRevenueIdr: 1000
    );
    expect($res2->nonHalalRevenueRatioBps)->toBe(1200)
        ->and($res2->revenueCompliant)->toBeFalse()
        ->and($res2->isCompliant)->toBeFalse();

    // dividen 1.000.000 dengan rasio 800 bps -> purifikasi 80.000
    $purify = $algo->purifyDividend(1_000_000, 800);
    expect($purify['purification_charity_idr'])->toBe(80_000)
        ->and($purify['net_halal_dividend_idr'])->toBe(920_000);
});

test('sertifikat rusak pada shariah screening memicu exception', function (): void {
    $corrupt = new ShariahScreeningResult(
        interestBearingDebtIdr: 400,
        totalAssetsIdr: 1000,
        debtRatioBps: 2000, // Rusak: seharusnya 4000 bps
        nonHalalRevenueIdr: 50,
        totalRevenueIdr: 1000,
        nonHalalRevenueRatioBps: 500,
        sectorCode: 'GENERAL_COMMERCE',
        sectorAllowed: true,
        debtCompliant: true,
        revenueCompliant: true,
        isCompliant: true
    );

    (new ShariahScreeningCertificateVerifier())->verify($corrupt);
})->throws(InvalidCertificateException::class);
