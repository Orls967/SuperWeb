<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Venue\Domain\Algorithms\TierCountdownPriceCertificate;
use Modules\Venue\Domain\Algorithms\TierCountdownPriceCertificateVerifier;
use Modules\Venue\Domain\Algorithms\TierCountdownPricer;
use PHPUnit\Framework\TestCase;

final class TierCountdownPricerTest extends TestCase
{
    public function test_prompt_vectors(): void
    {
        $floor = 280_000;
        $ceiling = 500_000;

        // Vektor 1: GA 350.000, terjual 8.500, hujan 2.000 -> 420.000
        $res1 = TierCountdownPricer::calculate(
            basePriceIdr: 350_000,
            demandBps: 8500,
            rainProbabilityBps: 2000,
            floorPriceIdr: $floor,
            ceilingPriceIdr: $ceiling
        );
        $this->assertSame(420_000, $res1['price_idr']);

        // Vektor 2: EB 250.000, terjual 3.000, hujan 7.000 -> 225.000 -> floor 280.000
        $res2 = TierCountdownPricer::calculate(
            basePriceIdr: 250_000,
            demandBps: 3000,
            rainProbabilityBps: 7000,
            floorPriceIdr: $floor,
            ceilingPriceIdr: $ceiling
        );
        $this->assertSame(280_000, $res2['price_idr']);

        // Vektor 3: Door 450.000, terjual 9.000 -> 540.000 -> ceiling 500.000
        $res3 = TierCountdownPricer::calculate(
            basePriceIdr: 450_000,
            demandBps: 9000,
            rainProbabilityBps: 1000,
            floorPriceIdr: $floor,
            ceilingPriceIdr: $ceiling
        );
        $this->assertSame(500_000, $res3['price_idr']);

        // Vektor 4: GA 350.000, terjual 7.999, hujan 6.000 -> 346.000
        $res4 = TierCountdownPricer::calculate(
            basePriceIdr: 350_000,
            demandBps: 7999,
            rainProbabilityBps: 6000,
            floorPriceIdr: $floor,
            ceilingPriceIdr: $ceiling
        );
        $this->assertSame(346_000, $res4['price_idr']);
    }

    public function test_corrupted_certificate_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $cert = new TierCountdownPriceCertificate(
            basePriceIdr: 350_000,
            demandBps: 8500,
            rainProbabilityBps: 2000,
            floorPriceIdr: 280_000,
            ceilingPriceIdr: 500_000,
            calculatedPriceIdr: 420_000,
            finalPriceIdr: 420_000,
            hmacSignature: 'tampered-signature'
        );

        TierCountdownPriceCertificateVerifier::verify($cert);
    }
}
