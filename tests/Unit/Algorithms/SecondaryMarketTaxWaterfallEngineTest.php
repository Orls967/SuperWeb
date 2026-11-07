<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallEngine;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallInput;
use Tests\TestCase;

final class SecondaryMarketTaxWaterfallEngineTest extends TestCase
{
    private SecondaryMarketTaxWaterfallEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new SecondaryMarketTaxWaterfallEngine;
    }

    public function test_conserves_integer_currency_without_float_leakage(): void
    {
        $input = new SecondaryMarketTaxWaterfallInput(
            resalePriceIdr: 1250000,
            originalFaceValueIdr: 1000000,
            taxRateBps: 1000, // 10% = 125,000
            platformFeeRateBps: 800, // 8% = 100,000
            promoterRoyaltyBps: 200 // 2% = 25,000
        );

        $result = $this->engine->calculateWaterfall($input);

        $this->assertSame(125000, $result->taxPbjtIdr);
        $this->assertSame(100000, $result->platformFeeIdr);
        $this->assertSame(25000, $result->promoterRoyaltyIdr);
        $this->assertSame(1000000, $result->sellerPayoutIdr);

        // Konservasi total
        $this->assertSame(
            1250000,
            $result->taxPbjtIdr + $result->platformFeeIdr + $result->promoterRoyaltyIdr + $result->sellerPayoutIdr
        );
        $this->assertTrue($result->certificate?->isValid());
    }

    public function test_handles_arbitrary_odd_prices_with_exact_conservation(): void
    {
        // Nilai ganjil untuk menguji pembulatan integer
        $input = new SecondaryMarketTaxWaterfallInput(
            resalePriceIdr: 1111111,
            originalFaceValueIdr: 1000000
        );

        $result = $this->engine->calculateWaterfall($input);

        $sum = $result->taxPbjtIdr + $result->platformFeeIdr + $result->promoterRoyaltyIdr + $result->sellerPayoutIdr;
        $this->assertSame(1111111, $sum);
        $this->assertTrue($result->certificate?->isValid());
    }
}
