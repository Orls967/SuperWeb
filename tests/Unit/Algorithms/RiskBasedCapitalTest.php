<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Solvency\RiskBasedCapital;
use Modules\Shared\Domain\Algorithms\Solvency\RiskBasedCapitalCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Solvency\SolvencyResult;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use Tests\TestCase;

final class RiskBasedCapitalTest extends TestCase
{
    public function test_vektor_prompt_rbc_dan_sertifikat_valid(): void
    {
        $rbc = new RiskBasedCapital;
        $verifier = new RiskBasedCapitalCertificateVerifier;

        $riskCharges = [
            'premium' => 300000000,
            'reserve' => 400000000,
            'market' => 200000000,
        ];
        $correlations = [
            'premium' => ['reserve' => 5000, 'market' => 2500],
            'reserve' => ['market' => 2500],
        ];

        $result = $rbc->compute($riskCharges, $correlations, 30000000, 1200000000);

        $this->assertSame('480000000000000000', $result->scaledSum);
        $this->assertSame(692820323, $result->diversifiedIdr);
        $this->assertSame(722820323, $result->rbcIdr);
        $this->assertSame(16601, $result->solvencyRatioBps);
        $this->assertTrue($verifier->verify($result));
    }

    public function test_sertifikat_rusak_rbc_melempar_exception(): void
    {
        $verifier = new RiskBasedCapitalCertificateVerifier;

        $corrupt = new SolvencyResult(
            riskChargesIdr: ['premium' => 300000000],
            correlationMatrixBps: ['premium' => ['premium' => 10000]],
            operationalIdr: 10000000,
            scaledSum: '9000000000000000',
            diversifiedIdr: 94868329,
            rbcIdr: 999999999, // Sabotase rbcIdr != diversified + operational
            availableCapitalIdr: 500000000,
            solvencyRatioBps: 5000
        );

        $this->expectException(InvalidCertificateException::class);
        $verifier->verify($corrupt);
    }
}

