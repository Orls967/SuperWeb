<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Utility\ChillerCopCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Utility\ChillerCopEfficiencyAnalyzer;
use Modules\Shared\Domain\Algorithms\Utility\ChillerCopResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ChillerCopEfficiencyTest extends TestCase
{
    public function test_chiller_cop_efficiency_analysis_and_verification(): void
    {
        $analyzer = new ChillerCopEfficiencyAnalyzer;
        $verifier = new ChillerCopCertificateVerifier;

        // Parameter: 1000 RT, 650 kW listrik, spesifikasi desain 0.65 kW/RT (6500 bps), 70 L/s, delta T 5.5 C (55 tenth C)
        $result = $analyzer->compute(
            coolingTons: 1000,
            electricalPowerKw: 650,
            designKwPerTonBps: 6500,
            chilledWaterFlowLps: 70,
            deltaTempTenthCelsius: 55
        );

        $this->assertSame(1000, $result->coolingTons);
        $this->assertSame(650, $result->electricalPowerKw);
        $this->assertSame(3517, $result->thermalOutputKw);
        $this->assertSame(6500, $result->actualKwPerTonBps);
        $this->assertSame(54107, $result->copBps); // COP 5.41
        $this->assertSame(0, $result->deviationBps);
        $this->assertSame('OPTIMAL', $result->operationalStatus);
        $this->assertTrue($verifier->verify($result));
    }

    public function test_chiller_cop_corrupted_certificate_fails(): void
    {
        $analyzer = new ChillerCopEfficiencyAnalyzer;
        $verifier = new ChillerCopCertificateVerifier;

        $result = $analyzer->compute(1000, 650, 6500, 70, 55);

        $tampered = new ChillerCopResult(
            coolingTons: $result->coolingTons,
            electricalPowerKw: $result->electricalPowerKw,
            thermalOutputKw: $result->thermalOutputKw,
            actualKwPerTonBps: $result->actualKwPerTonBps,
            copBps: $result->copBps,
            deviationBps: $result->deviationBps,
            chilledWaterFlowLps: $result->chilledWaterFlowLps,
            deltaTempTenthCelsius: $result->deltaTempTenthCelsius,
            operationalStatus: $result->operationalStatus,
            certificateHash: 'corrupted-hash',
            canonicalPayload: $result->canonicalPayload,
        );

        $this->expectException(RuntimeException::class);
        $verifier->verify($tampered);
    }
}
