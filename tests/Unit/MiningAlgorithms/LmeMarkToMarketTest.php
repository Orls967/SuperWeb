<?php

declare(strict_types=1);

namespace Tests\Unit\MiningAlgorithms;

use Modules\Shared\Domain\Algorithms\MetalsTrading\LmeMarketQuote;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LmeMetalMarkToMarketValuator;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LmeMetalMarkToMarketVerifier;
use Tests\TestCase;

class LmeMarkToMarketTest extends TestCase
{
    public function test_lme_mark_to_market_valuates_short_hedge_gain_and_loss(): void
    {
        $valuator = new LmeMetalMarkToMarketValuator;
        $verifier = new LmeMetalMarkToMarketVerifier;

        // Quote harga LME turun (gain untuk short hedge)
        $quoteGain = new LmeMarketQuote(
            metalSymbol: 'LME-NI',
            cashPriceUsdCentsPerTon: 1600000, // $16,000
            threeMonthPriceUsdCentsPerTon: 1612000,
            exchangeRateIdrPerUsd: 16250
        );

        // Entry harga $16,500/ton, 100 MT
        $resGain = $valuator->valuePosition(
            positionType: 'SHORT',
            metricTons: 100.0,
            entryPriceUsdCentsPerTon: 1650000,
            quote: $quoteGain
        );

        $this->assertEquals('SHORT', $resGain->positionType);
        $this->assertGreaterThan(0, $resGain->unrealizedPnlUsdCents); // +$50,000 profit
        $this->assertGreaterThan(0, $resGain->unrealizedPnlIdr);
        $this->assertFalse($resGain->isMarginCallTriggered);
        $this->assertTrue($verifier->verify($resGain, $quoteGain));

        // Quote harga LME melonjak tinggi ke $18,500 (loss besar -> memicu margin call)
        $quoteLoss = new LmeMarketQuote(
            metalSymbol: 'LME-NI',
            cashPriceUsdCentsPerTon: 1850000,
            threeMonthPriceUsdCentsPerTon: 1860000,
            exchangeRateIdrPerUsd: 16250
        );

        $resLoss = $valuator->valuePosition(
            positionType: 'SHORT',
            metricTons: 100.0,
            entryPriceUsdCentsPerTon: 1650000,
            quote: $quoteLoss
        );

        $this->assertLessThan(0, $resLoss->unrealizedPnlUsdCents);
        $this->assertLessThan(0, $resLoss->unrealizedPnlIdr);
        $this->assertTrue($resLoss->isMarginCallTriggered);
        $this->assertGreaterThan(0, $resLoss->variationMarginRequiredIdr);
        $this->assertTrue($verifier->verify($resLoss, $quoteLoss));
    }
}
