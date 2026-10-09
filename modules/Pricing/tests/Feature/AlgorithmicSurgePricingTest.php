<?php

namespace Modules\Pricing\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pricing\Application\Services\AlgorithmicSurgePricingService;
use Modules\Pricing\Domain\Models\PriceTick;
use Tests\TestCase;

class AlgorithmicSurgePricingTest extends TestCase
{
    use RefreshDatabase;

    protected AlgorithmicSurgePricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = app(AlgorithmicSurgePricingService::class);
    }

    public function test_81_3_strict_floor_and_ceiling_guardrails(): void
    {
        $hpp = 100_000;
        $het = 180_000;

        // 1. Extreme low demand index 0.2 -> raw price = 24k -> clamped to floor (115k)
        $low = $this->pricingService->computeTickPrice(
            sku: 'PART-BRAKE-01',
            baseHppIdr: $hpp,
            hetCeilingIdr: $het,
            demandIndex: 0.2
        );
        $this->assertEquals(115_000, $low['effective_price_idr']);
        $this->assertEquals(115_000, $low['floor_price_idr']);

        // 2. Extreme high demand index 3.0 -> raw price = 360k -> clamped to ceiling (180k)
        $high = $this->pricingService->computeTickPrice(
            sku: 'PART-BRAKE-01',
            baseHppIdr: $hpp,
            hetCeilingIdr: $het,
            demandIndex: 3.0
        );
        $this->assertEquals(180_000, $high['effective_price_idr']);
        $this->assertEquals(180_000, $high['ceiling_price_idr']);
        $this->assertTrue($high['is_surge']);
    }

    public function test_81_3_b2b_contract_price_always_beats_dynamic_surge(): void
    {
        $hpp = 100_000;
        $het = 250_000;
        $contractPrice = 135_000;

        // Even with huge surge demand (demandIndex 2.5), contract price remains fixed
        $result = $this->pricingService->computeTickPrice(
            sku: 'PART-FILTER-02',
            baseHppIdr: $hpp,
            hetCeilingIdr: $het,
            demandIndex: 2.5,
            contractPriceIdr: $contractPrice
        );

        $this->assertEquals(135_000, $result['effective_price_idr']);
        $this->assertEquals('CONTRACT_PRICE', $result['source']);
        $this->assertFalse($result['is_surge']);
    }

    public function test_81_1_and_81_4_tick_idempotency_and_immutable_quote_freeze(): void
    {
        $now = Carbon::parse('2026-10-18 10:15:30');

        // 1. Record tick idempotently
        $tick1 = $this->pricingService->recordPriceTick(
            sku: 'TIRE-SPORT-18',
            tickTime: $now,
            priceIdr: 1_250_000,
            demandIndex: 1.45,
            driverSource: 'DEMAND_SURGE'
        );

        $tick2 = $this->pricingService->recordPriceTick(
            sku: 'TIRE-SPORT-18',
            tickTime: $now,
            priceIdr: 1_250_000,
            demandIndex: 1.45,
            driverSource: 'DEMAND_SURGE'
        );

        $this->assertEquals($tick1->id, $tick2->id);
        $this->assertEquals(1, PriceTick::where('sku', 'TIRE-SPORT-18')->count());

        // 2. Freeze quote with cryptographic timelock
        $quote = $this->pricingService->freezePriceQuote(
            sku: 'TIRE-SPORT-18',
            effectivePriceIdr: 1_250_000,
            floorPriceIdr: 1_000_000,
            ceilingPriceIdr: 1_600_000,
            lockMinutes: 15
        );

        $this->assertEquals('LOCKED', $quote->status);
        $this->assertEquals(1_250_000, $quote->frozen_price_idr);
        $this->assertNotNull($quote->quote_hash);
        $this->assertTrue($quote->locked_until->isFuture());
    }
}
