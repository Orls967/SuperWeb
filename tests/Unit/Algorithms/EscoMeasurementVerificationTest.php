<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Utility\EscoMeasurementVerification;
use Modules\Shared\Domain\Algorithms\Utility\EscoMeasurementVerificationCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Utility\EscoMeasurementVerificationResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EscoMeasurementVerificationTest extends TestCase
{
    public function test_esco_measurement_verification_satisfies_prompt_vector(): void
    {
        $algorithm = new EscoMeasurementVerification;

        // Vektor prompt:
        // baseline 100.000 kWh, faktor 1,1 (11000 bps) -> 110.000;
        // aktual 88.000 -> hemat 22.000 kWh * 1.400 = 30.800.000;
        // ESCO 70% (7000 bps) = 21.560.000, klien 9.240.000.
        // District cooling: termal 30.000 kWh_th, listrik plant 6.000 kWh -> COP 5,0 (500 bps);
        // pembanding AC individual COP 3,0 (300 bps) -> 10.000 kWh; hemat 4.000 kWh.
        $result = $algorithm->calculate(
            baselineKwh: 100_000,
            cddFactorBps: 11_000,
            actualKwh: 88_000,
            rateIdrPerKwh: 1_400,
            escoShareBps: 7_000,
            thermalKwhTh: 30_000,
            plantElectricKwh: 6_000,
            baselineCopBps: 300
        );

        $this->assertSame(100_000, $result->baselineKwh);
        $this->assertSame(11_000, $result->cddFactorBps);
        $this->assertSame(110_000, $result->adjustedBaselineKwh);
        $this->assertSame(88_000, $result->actualKwh);
        $this->assertSame(22_000, $result->grossSavingsKwh);
        $this->assertSame(30_800_000, $result->totalSavingsIdr);
        $this->assertSame(21_560_000, $result->escoSavingsShareIdr);
        $this->assertSame(9_240_000, $result->clientSavingsShareIdr);
        $this->assertSame(500, $result->copBps);
        $this->assertSame(4_000, $result->districtCoolingSavingsKwh);
        $this->assertTrue($result->certified);
    }

    public function test_esco_corrupted_certificate_throws_exception(): void
    {
        $verifier = new EscoMeasurementVerificationCertificateVerifier;

        $this->expectException(RuntimeException::class);

        // Corrupted: sum shares (20_000_000 + 9_000_000 != 30_800_000)
        $corrupted = new EscoMeasurementVerificationResult(
            baselineKwh: 100_000,
            cddFactorBps: 11_000,
            adjustedBaselineKwh: 110_000,
            actualKwh: 88_000,
            grossSavingsKwh: 22_000,
            rateIdrPerKwh: 1_400,
            totalSavingsIdr: 30_800_000,
            escoShareBps: 7_000,
            escoSavingsShareIdr: 20_000_000,
            clientSavingsShareIdr: 9_000_000,
            certified: false
        );

        $verifier->verify($corrupted);
    }
}
