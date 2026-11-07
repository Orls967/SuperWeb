<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Mining\Domain\Algorithms\CoalPriceAdjuster;
use Modules\Mining\Domain\Algorithms\CoalPriceAdjusterCertificateVerifier;
use Modules\Mining\Domain\Algorithms\CoalPriceAdjusterResult;
use Tests\TestCase;

class CoalPriceAdjusterTest extends TestCase
{
    public function test_matches_prompt_vector(): void
    {
        $adjuster = new CoalPriceAdjuster;

        // Vektor: dasar 8.500 sen, GAR 4.850/5.000 -> 8.245; abu 9,3% (batas 8,0%) -> -65;
        // TS 0,9% (batas 0,8%) -> -100; harga 8.080 sen/t x 55.000 t = 444.400.000 sen (USD 4.444.000).
        $res = $adjuster->adjust(
            basePriceCents: 8500,
            actualGarKcal: 4850,
            referenceGarKcal: 5000,
            actualAshPermil: 93,       // 9.3%
            baseAshLimitPermil: 80,    // 8.0%
            actualSulfurPermil: 9,     // 0.9%
            baseSulfurLimitPermil: 8,  // 0.8%
            volumeTons: 55000
        );

        $this->assertFalse($res->isRejected);
        $this->assertSame(8245, $res->garAdjustedPriceCents);
        $this->assertSame(65, $res->ashPenaltyCents);
        $this->assertSame(100, $res->sulfurPenaltyCents);
        $this->assertSame(8080, $res->finalPriceCentsPerTon);
        $this->assertSame(444400000, $res->totalInvoiceUsdCents);
    }

    public function test_excessive_ash_or_sulfur_triggers_cargo_rejection(): void
    {
        $adjuster = new CoalPriceAdjuster;

        $res = $adjuster->adjust(
            basePriceCents: 8500,
            actualGarKcal: 4850,
            referenceGarKcal: 5000,
            actualAshPermil: 160,      // 16.0% > 15.0% reject
            baseAshLimitPermil: 80,
            actualSulfurPermil: 9,
            baseSulfurLimitPermil: 8,
            volumeTons: 55000,
            rejectAshPermil: 150
        );

        $this->assertTrue($res->isRejected);
        $this->assertSame(0, $res->finalPriceCentsPerTon);
        $this->assertSame(0, $res->totalInvoiceUsdCents);
        $this->assertStringContainsString('Kadar abu', $res->rejectionReason);
    }

    public function test_tampered_certificate_throws_exception(): void
    {
        $verifier = new CoalPriceAdjusterCertificateVerifier;

        $validResult = (new CoalPriceAdjuster)->adjust(
            basePriceCents: 8500,
            actualGarKcal: 4850,
            referenceGarKcal: 5000,
            actualAshPermil: 93,
            baseAshLimitPermil: 80,
            actualSulfurPermil: 9,
            baseSulfurLimitPermil: 8,
            volumeTons: 55000
        );

        $tamperedCert = $validResult->certificate;
        $tamperedCert['canonical_hash'] = 'corrupted_coal_hash_9999';

        $tamperedResult = new CoalPriceAdjusterResult(
            basePriceCents: $validResult->basePriceCents,
            actualGarKcal: $validResult->actualGarKcal,
            referenceGarKcal: $validResult->referenceGarKcal,
            actualAshPermil: $validResult->actualAshPermil,
            baseAshLimitPermil: $validResult->baseAshLimitPermil,
            actualSulfurPermil: $validResult->actualSulfurPermil,
            baseSulfurLimitPermil: $validResult->baseSulfurLimitPermil,
            volumeTons: $validResult->volumeTons,
            garAdjustedPriceCents: $validResult->garAdjustedPriceCents,
            ashPenaltyCents: $validResult->ashPenaltyCents,
            sulfurPenaltyCents: $validResult->sulfurPenaltyCents,
            finalPriceCentsPerTon: $validResult->finalPriceCentsPerTon,
            totalInvoiceUsdCents: $validResult->totalInvoiceUsdCents,
            isRejected: $validResult->isRejected,
            rejectionReason: $validResult->rejectionReason,
            certificate: $tamperedCert
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Integritas sertifikat harga batubara rusak');

        $verifier->verify($tamperedResult);
    }
}
