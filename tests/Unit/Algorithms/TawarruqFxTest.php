<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Shared\Domain\Algorithms\Islamic\TawarruqFx;
use Modules\Shared\Domain\Algorithms\Islamic\TawarruqFxCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Islamic\TawarruqFxResult;

test('vektor algoritma tawarruq fx konversi valas syariah', function () {
    $calculator = new TawarruqFx;
    $verifier = new TawarruqFxCertificateVerifier;

    // IDR 1,550,000,000 -> target USD @ 15,500.00 IDR/USD (1550000 bps)
    // expected target received: 1,550,000,000 * 10000 / 1550000 = 10,000,000 cents = $100,000.00 USD
    // Tenor 90 hari, margin 6.00% (600 bps)
    // expected markup: 1,550,000,000 * 600 * 90 / (365 * 10000) = 22,931,506 IDR cents
    $orderNumber = 'TWQ-FX-2026-001';
    $sourcePrincipalCents = 1_550_000_000;
    $spotRateBps = 1_550_000;
    $tenorDays = 90;
    $markupRateBps = 600;
    $brokerFeeCents = 2_500_000;
    $serial = 'BURSA-LME-ALUM-88392';
    $now = CarbonImmutable::parse('2026-11-06 11:00:00');

    $result = $calculator->calculate(
        orderNumber: $orderNumber,
        sourceCurrency: 'IDR',
        sourcePrincipalCents: $sourcePrincipalCents,
        targetCurrency: 'USD',
        spotRateBps: $spotRateBps,
        tenorDays: $tenorDays,
        markupRateBps: $markupRateBps,
        brokerFeeCents: $brokerFeeCents,
        commodityLotSerial: $serial,
        exchangeName: 'Bursa Komoditas Syariah Internasional',
        evaluatedAt: $now
    );

    expect($result->targetReceivedCents)->toBe(10_000_000)
        ->and($result->markupProfitCents)->toBe(22_931_506)
        ->and($result->totalDeferredPriceCents)->toBe(1_550_000_000 + 22_931_506 + 2_500_000)
        ->and($result->commodityLotSerial)->toBe($serial)
        ->and($verifier->verify($result))->toBeTrue();
});

test('sertifikat rusak pada tawarruq fx memicu exception', function () {
    $calculator = new TawarruqFx;
    $verifier = new TawarruqFxCertificateVerifier;

    $result = $calculator->calculate(
        orderNumber: 'TWQ-FX-002',
        sourceCurrency: 'IDR',
        sourcePrincipalCents: 100_000_000,
        targetCurrency: 'USD',
        spotRateBps: 1_600_000,
        tenorDays: 30,
        markupRateBps: 500,
        brokerFeeCents: 500_000,
        commodityLotSerial: 'BURSA-LOT-9921',
        exchangeName: 'ICDX',
    );

    $corrupted = new TawarruqFxResult(
        orderNumber: $result->orderNumber,
        sourceCurrency: $result->sourceCurrency,
        sourcePrincipalCents: $result->sourcePrincipalCents,
        targetCurrency: $result->targetCurrency,
        targetReceivedCents: 99999999, // tampered
        spotRateBps: $result->spotRateBps,
        tenorDays: $result->tenorDays,
        markupRateBps: $result->markupRateBps,
        markupProfitCents: $result->markupProfitCents,
        brokerFeeCents: $result->brokerFeeCents,
        totalDeferredPriceCents: $result->totalDeferredPriceCents,
        effectiveRateBps: $result->effectiveRateBps,
        commodityLotSerial: $result->commodityLotSerial,
        exchangeName: $result->exchangeName,
        auditMetadata: $result->auditMetadata,
        evaluatedAt: $result->evaluatedAt,
        certificateHash: $result->certificateHash,
    );

    expect(fn () => $verifier->verify($corrupted))->toThrow(RuntimeException::class);
});
