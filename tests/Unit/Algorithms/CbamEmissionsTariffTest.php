<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Utility\CbamEmissionsCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Utility\CbamEmissionsTariff;
use Modules\Shared\Domain\Algorithms\Utility\CbamEmissionsTariffResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CbamEmissionsTariffTest extends TestCase
{
    public function test_cbam_emissions_tariff_calculation_and_verification(): void
    {
        $calculator = new CbamEmissionsTariff;
        $verifier = new CbamEmissionsCertificateVerifier;

        // Ekspor 10.000 kg baja (10 ton).
        // Intensitas spesifik = 2.100 kg CO2e / ton.
        // Benchmark EU = 1.300 kg CO2e / ton -> selisih = 800 kg CO2e / ton.
        // Gross embedded = 21.000 kg CO2e.
        // Taxable emissions = 8.000 kg CO2e.
        // Free allowance 25% (2500 bps) -> potongan 2.000 kg -> Net payable = 6.000 kg CO2e (6 ton).
        // Harga karbon EU ETS = €70,00 / ton (7000 sen). Liabilitas EUR = 6 * 7000 = 42.000 sen (€420,00).
        // Kurs EUR = Rp 17.500 (1750000 minor per 100 sen).
        // Liabilitas IDR = (42000 * 1750000) / 100 = Rp 735.000.000 / 100 = Rp 7.350.000.
        $result = $calculator->calculate(
            productTonnageKg: 10_000,
            specificEmissionFactorKgCo2ePerTon: 2100,
            euBenchmarkFactorKgCo2ePerTon: 1300,
            freeAllowancePercentageBps: 2500,
            carbonPriceEurMinorPerTon: 7000,
            eurToIdrExchangeRateMinor: 1_750_000
        );

        $this->assertSame(10_000, $result->productTonnageKg);
        $this->assertSame(21_000, $result->grossEmbeddedEmissionsKgCo2e);
        $this->assertSame(6_000, $result->netPayableEmissionsKgCo2e);
        $this->assertSame(2_000, $result->freeAllowanceDeductionKgCo2e);
        $this->assertSame(42_000, $result->liabilityEurMinor);
        $this->assertSame(735_000_000, $result->liabilityIdr);
        $this->assertTrue($verifier->verify($result));
    }

    public function test_cbam_tampered_fails(): void
    {
        $calculator = new CbamEmissionsTariff;
        $verifier = new CbamEmissionsCertificateVerifier;

        $result = $calculator->calculate(10_000, 2100, 1300, 2500, 7000, 1_750_000);

        $tampered = new CbamEmissionsTariffResult(
            productTonnageKg: $result->productTonnageKg,
            specificEmissionFactorKgCo2ePerTon: $result->specificEmissionFactorKgCo2ePerTon,
            euBenchmarkFactorKgCo2ePerTon: $result->euBenchmarkFactorKgCo2ePerTon,
            grossEmbeddedEmissionsKgCo2e: $result->grossEmbeddedEmissionsKgCo2e,
            netPayableEmissionsKgCo2e: $result->netPayableEmissionsKgCo2e,
            freeAllowanceDeductionKgCo2e: $result->freeAllowanceDeductionKgCo2e,
            liabilityEurMinor: $result->liabilityEurMinor,
            liabilityIdr: $result->liabilityIdr,
            certificateHash: 'corrupted-hash',
            canonicalPayload: $result->canonicalPayload,
        );

        $this->expectException(RuntimeException::class);
        $verifier->verify($tampered);
    }
}
