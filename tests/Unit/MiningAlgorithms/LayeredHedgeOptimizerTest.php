<?php

declare(strict_types=1);

namespace Tests\Unit\MiningAlgorithms;

use Modules\Shared\Domain\Algorithms\MetalsTrading\LayeredHedgeHorizonQuote;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LayeredProductionHedgeOptimizer;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LayeredProductionHedgeVerifier;
use Tests\TestCase;

final class LayeredHedgeOptimizerTest extends TestCase
{
    public function test_layered_production_hedge_optimizes_multi_horizon_portfolio(): void
    {
        $optimizer = new LayeredProductionHedgeOptimizer;
        $verifier = new LayeredProductionHedgeVerifier;

        $quotes = [
            new LayeredHedgeHorizonQuote(
                monthTenor: '2026-12',
                plannedProductionTons: 1000.0,
                existingHedgedTons: 400.0,
                targetHedgeRatio: 0.70,
                lmeForwardPriceUsdPerTon: 18000,
                exchangeRateIdrPerUsd: 16000
            ),
            new LayeredHedgeHorizonQuote(
                monthTenor: '2027-01',
                plannedProductionTons: 1200.0,
                existingHedgedTons: 300.0,
                targetHedgeRatio: 0.50,
                lmeForwardPriceUsdPerTon: 18200,
                exchangeRateIdrPerUsd: 16100
            ),
        ];

        $result = $optimizer->optimize($quotes, 0.20);

        $this->assertEquals(2200.0, $result->aggregatePlannedProductionTons);
        $this->assertEquals(700.0, $result->aggregateExistingHedgedTons);
        $this->assertEquals(600.0, $result->aggregateNewHedgeRecommendedTons);
        $this->assertTrue($result->isHedgePolicyCompliant);
        $this->assertGreaterThan(0, $result->securedNotionalValueIdr);
        $this->assertArrayHasKey('shock_minus_20_pct', $result->stressTestDownsideLossIdr);

        $this->assertTrue($verifier->verify($result, $quotes));
    }
}
