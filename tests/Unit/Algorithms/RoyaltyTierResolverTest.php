<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Mining\Domain\Algorithms\RoyaltyTierResolver;
use Modules\Mining\Domain\Algorithms\RoyaltyTierResolverCertificateVerifier;
use Modules\Mining\Domain\Algorithms\RoyaltyTierResolverResult;
use Tests\TestCase;

class RoyaltyTierResolverTest extends TestCase
{
    public function test_matches_prompt_vectors_and_boundary_conditions(): void
    {
        $resolver = new RoyaltyTierResolver;

        // Vektor 1: Batubara open pit 5.000 kcal, HBA 85,00 (8500 sen) -> 850 bps;
        // 50.000 t x USD 72,50 (7250 sen) -> royalti 30.812.500 sen (USD 308.125,00);
        // kurs 16.250 -> Rp 5.007.031.250
        $res1 = $resolver->resolve(
            commodity: 'coal',
            productType: 'raw_coal',
            miningMethod: 'open_pit',
            calorieKcal: 5000,
            hbaOrHmaCents: 8500,
            volumeTons: 50000,
            benchmarkPriceCents: 7250,
            exchangeRateIdr: 16250
        );

        $this->assertSame(850, $res1->royaltyRateBps);
        $this->assertSame(30812500, $res1->royaltyUsdCents);
        $this->assertSame(5007031250, $res1->royaltyIdr);

        // Vektor 2: Bijih nikel HMA 15.200 (1520000 sen) -> 1.400 bps;
        // 10.000 wmt x USD 38,25 (3825 sen) -> 5.355.000 sen -> Rp 870.187.500
        $res2 = $resolver->resolve(
            commodity: 'nickel',
            productType: 'ore',
            miningMethod: 'open_pit',
            calorieKcal: 0,
            hbaOrHmaCents: 1520000,
            volumeTons: 10000,
            benchmarkPriceCents: 3825,
            exchangeRateIdr: 16250
        );

        $this->assertSame(1400, $res2->royaltyRateBps);
        $this->assertSame(5355000, $res2->royaltyUsdCents);
        $this->assertSame(870187500, $res2->royaltyIdr);

        // Vektor 3 (Tepi): HBA tepat 70,00 (7000 sen) -> tier 70-<90 (850 bps);
        // HMA tepat 18.000 (1800000 sen) -> 1.500 bps
        $edgeCoal = $resolver->resolve(
            commodity: 'coal',
            productType: 'raw_coal',
            miningMethod: 'open_pit',
            calorieKcal: 5000,
            hbaOrHmaCents: 7000,
            volumeTons: 1000,
            benchmarkPriceCents: 7000,
            exchangeRateIdr: 16000
        );
        $this->assertSame(850, $edgeCoal->royaltyRateBps);

        $edgeNickel = $resolver->resolve(
            commodity: 'nickel',
            productType: 'ore',
            miningMethod: 'open_pit',
            calorieKcal: 0,
            hbaOrHmaCents: 1800000,
            volumeTons: 1000,
            benchmarkPriceCents: 3000,
            exchangeRateIdr: 16000
        );
        $this->assertSame(1500, $edgeNickel->royaltyRateBps);
    }

    public function test_tampered_certificate_throws_exception(): void
    {
        $verifier = new RoyaltyTierResolverCertificateVerifier;

        $validResult = (new RoyaltyTierResolver)->resolve(
            commodity: 'coal',
            productType: 'raw_coal',
            miningMethod: 'open_pit',
            calorieKcal: 5000,
            hbaOrHmaCents: 8500,
            volumeTons: 50000,
            benchmarkPriceCents: 7250,
            exchangeRateIdr: 16250
        );

        // Manipulasi sertifikat hash
        $tamperedCert = $validResult->certificate;
        $tamperedCert['canonical_hash'] = 'corrupted_hash_value_12345';

        $tamperedResult = new RoyaltyTierResolverResult(
            commodity: $validResult->commodity,
            productType: $validResult->productType,
            miningMethod: $validResult->miningMethod,
            calorieKcal: $validResult->calorieKcal,
            hbaOrHmaCents: $validResult->hbaOrHmaCents,
            volumeTons: $validResult->volumeTons,
            benchmarkPriceCents: $validResult->benchmarkPriceCents,
            exchangeRateIdr: $validResult->exchangeRateIdr,
            royaltyRateBps: $validResult->royaltyRateBps,
            royaltyUsdCents: $validResult->royaltyUsdCents,
            royaltyIdr: $validResult->royaltyIdr,
            certificate: $tamperedCert
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Integritas sertifikat royalti rusak');

        $verifier->verify($tamperedResult);
    }
}
