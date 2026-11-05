<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Shared\Domain\Algorithms\Islamic\SalamPositionBook;
use Modules\Shared\Domain\Algorithms\Islamic\SalamPositionBookCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Islamic\SalamPositionBookResult;

test('vektor algoritma salam position book komoditas dan parallel hedge', function () {
    $book = new SalamPositionBook;
    $verifier = new SalamPositionBookCertificateVerifier;

    $salamContracts = [
        [
            'contract_id' => 'slm-001',
            'commodity_code' => 'CPO',
            'grade' => 'SUPER_A',
            'quantity_ordered' => 100, // 100 ton
            'quantity_delivered' => 40,  // 40 ton delivered
            'advance_payment_cents' => 1_200_000_000, // 12jt/ton = 1.2M IDR
            'promised_delivery_date' => '2026-12-01',
        ],
        [
            'contract_id' => 'slm-002',
            'commodity_code' => 'CPO',
            'grade' => 'SUPER_A',
            'quantity_ordered' => 50, // 50 ton
            'quantity_delivered' => 0,
            'advance_payment_cents' => 625_000_000, // 12.5jt/ton
            'promised_delivery_date' => '2026-12-15',
        ],
    ];

    $parallelHedges = [
        [
            'hedge_id' => 'hdg-001',
            'commodity_code' => 'CPO',
            'quantity_hedged' => 80, // 80 ton hedged to off-taker
            'agreed_sale_price_cents' => 1_120_000_000, // 14jt/ton = 1.12M IDR
            'delivery_date' => '2026-12-20',
        ],
    ];

    // Spot price 13jt/ton (13_000_000 cents/ton)
    $spotPriceCents = 13_000_000;
    $now = CarbonImmutable::parse('2026-11-06 10:00:00');

    $result = $book->evaluate(
        commodityCode: 'CPO',
        salamContracts: $salamContracts,
        parallelHedges: $parallelHedges,
        spotPricePerUnitCents: $spotPriceCents,
        evaluatedAt: $now
    );

    expect($result->totalLongQuantity)->toBe(150)
        ->and($result->totalShortQuantity)->toBe(80)
        ->and($result->netOpenPositionQuantity)->toBe(70)
        ->and($result->totalLongCostCents)->toBe(1_825_000_000)
        ->and($result->totalShortCommitmentCents)->toBe(1_120_000_000)
        ->and($result->markToMarketValueCents)->toBe(150 * 13_000_000)
        ->and($result->fulfillmentRatioBps)->toBe((int) intdiv(40 * 10000, 150))
        ->and($verifier->verify($result))->toBeTrue();
});

test('sertifikat rusak pada salam position book memicu exception', function () {
    $book = new SalamPositionBook;
    $verifier = new SalamPositionBookCertificateVerifier;

    $result = $book->evaluate(
        commodityCode: 'COFFEE',
        salamContracts: [
            [
                'contract_id' => 'slm-010',
                'commodity_code' => 'COFFEE',
                'grade' => 'ARABICA_G1',
                'quantity_ordered' => 20,
                'quantity_delivered' => 10,
                'advance_payment_cents' => 200_000_000,
                'promised_delivery_date' => '2026-12-01',
            ],
        ],
        parallelHedges: [],
        spotPricePerUnitCents: 11_000_000,
    );

    $corruptedResult = new SalamPositionBookResult(
        commodityCode: $result->commodityCode,
        totalLongQuantity: 99999, // tampered
        totalShortQuantity: $result->totalShortQuantity,
        netOpenPositionQuantity: $result->netOpenPositionQuantity,
        totalLongCostCents: $result->totalLongCostCents,
        totalShortCommitmentCents: $result->totalShortCommitmentCents,
        markToMarketValueCents: $result->markToMarketValueCents,
        unrealizedPnlCents: $result->unrealizedPnlCents,
        fulfillmentRatioBps: $result->fulfillmentRatioBps,
        contractSummaries: $result->contractSummaries,
        evaluatedAt: $result->evaluatedAt,
        certificateHash: $result->certificateHash,
    );

    expect(fn () => $verifier->verify($corruptedResult))->toThrow(RuntimeException::class);
});
