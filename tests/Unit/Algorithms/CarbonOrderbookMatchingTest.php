<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Utility\CarbonOrderbookCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Utility\CarbonOrderbookMatching;
use Modules\Shared\Domain\Algorithms\Utility\CarbonOrderbookMatchingResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CarbonOrderbookMatchingTest extends TestCase
{
    public function test_carbon_orderbook_matching_price_time_priority(): void
    {
        $engine = new CarbonOrderbookMatching;
        $verifier = new CarbonOrderbookCertificateVerifier;

        // Buy order 1: Rp 350.000 / ton, 5.000 kg (5 ton), waktu t=100
        // Buy order 2: Rp 360.000 / ton (agresif), 2.000 kg (2 ton), waktu t=110
        $buyOrders = [
            ['id' => 'B-1', 'party_id' => 'PARTY-B1', 'price_minor_per_ton' => 350_000, 'quantity_kg_co2e' => 5_000, 'timestamp_epoch' => 100],
            ['id' => 'B-2', 'party_id' => 'PARTY-B2', 'price_minor_per_ton' => 360_000, 'quantity_kg_co2e' => 2_000, 'timestamp_epoch' => 110],
        ];

        // Sell order 1: Rp 340.000 / ton, 3.000 kg (3 ton), waktu t=90
        // Sell order 2: Rp 355.000 / ton, 4.000 kg (4 ton), waktu t=95
        $sellOrders = [
            ['id' => 'S-1', 'party_id' => 'PARTY-S1', 'price_minor_per_ton' => 340_000, 'quantity_kg_co2e' => 3_000, 'timestamp_epoch' => 90],
            ['id' => 'S-2', 'party_id' => 'PARTY-S2', 'price_minor_per_ton' => 355_000, 'quantity_kg_co2e' => 4_000, 'timestamp_epoch' => 95],
        ];

        $result = $engine->match($buyOrders, $sellOrders);

        // B-2 (harga 360rb) mencocokkan S-1 (harga 340rb) sebanyak 2 ton pada harga 340rb = Rp 680.000
        // B-1 (harga 350rb) mencocokkan sisa S-1 sebanyak 1 ton pada harga 340rb = Rp 340.000
        // Sisa B-1 adalah 4 ton (pada harga 350rb), sedangkan S-2 menawarkan 355rb -> tidak cocok (spread negatif)
        $this->assertCount(2, $result->matchedTrades);
        $this->assertSame(3_000, $result->totalTradedKg);
        $this->assertSame(1_020_000, $result->totalTradeValueIdr);
        $this->assertTrue($verifier->verify($result));
    }

    public function test_carbon_orderbook_tampered_hash_fails(): void
    {
        $engine = new CarbonOrderbookMatching;
        $verifier = new CarbonOrderbookCertificateVerifier;

        $buy = [['id' => 'B-1', 'party_id' => 'P1', 'price_minor_per_ton' => 300_000, 'quantity_kg_co2e' => 1_000, 'timestamp_epoch' => 100]];
        $sell = [['id' => 'S-1', 'party_id' => 'P2', 'price_minor_per_ton' => 300_000, 'quantity_kg_co2e' => 1_000, 'timestamp_epoch' => 100]];

        $result = $engine->match($buy, $sell);

        $tampered = new CarbonOrderbookMatchingResult(
            matchedTrades: $result->matchedTrades,
            totalTradedKg: $result->totalTradedKg,
            totalTradeValueIdr: $result->totalTradeValueIdr,
            remainingBids: $result->remainingBids,
            remainingAsks: $result->remainingAsks,
            certificateHash: 'invalid-hash-xxx',
            canonicalPayload: $result->canonicalPayload,
        );

        $this->expectException(RuntimeException::class);
        $verifier->verify($tampered);
    }
}
