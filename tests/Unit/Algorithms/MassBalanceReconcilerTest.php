<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Metallurgy\MassBalanceReconciler;
use Modules\Shared\Domain\Algorithms\Metallurgy\MassBalanceReconcilerCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Metallurgy\MassBalanceReconcilerResult;
use Tests\TestCase;

class MassBalanceReconcilerTest extends TestCase
{
    public function test_matches_prompt_vector(): void
    {
        $reconciler = new MassBalanceReconciler;

        // Vektor (kg Ni): feed 18.000, konsentrat 14.400, tailing 3.520; varians 4/1/3
        // -> r = 80 -> 17.960 / 14.410 / 3.550 (seimbang); recovery 8.023 bps.
        $res = $reconciler->reconcile(
            feedKg: 18000,
            concentrateKg: 14400,
            tailingKg: 3520,
            variances: ['feed' => 4, 'concentrate' => 1, 'tailing' => 3],
            toleranceKg: 100
        );

        $this->assertSame(80, $res->discrepancyKg);
        $this->assertSame(17960, $res->reconciledFeedKg);
        $this->assertSame(14410, $res->reconciledConcentrateKg);
        $this->assertSame(3550, $res->reconciledTailingKg);
        $this->assertSame(8023, $res->recoveryBps);
        $this->assertFalse($res->requiresInvestigation);
    }

    public function test_large_discrepancy_triggers_investigation(): void
    {
        $reconciler = new MassBalanceReconciler;

        $res = $reconciler->reconcile(
            feedKg: 18000,
            concentrateKg: 13000,
            tailingKg: 3000,
            variances: ['feed' => 4, 'concentrate' => 1, 'tailing' => 3],
            toleranceKg: 500
        );

        $this->assertSame(2000, $res->discrepancyKg);
        $this->assertTrue($res->requiresInvestigation);
        $this->assertTrue($res->requiresApproval);
    }

    public function test_tampered_certificate_throws_exception(): void
    {
        $verifier = new MassBalanceReconcilerCertificateVerifier;

        $validResult = (new MassBalanceReconciler)->reconcile(
            feedKg: 18000,
            concentrateKg: 14400,
            tailingKg: 3520
        );

        // Manipulasi sertifikat hash
        $tamperedCert = $validResult->certificate;
        $tamperedCert['canonical_hash'] = 'corrupted_hash_value_54321';

        $tamperedResult = new MassBalanceReconcilerResult(
            rawFeedKg: $validResult->rawFeedKg,
            rawConcentrateKg: $validResult->rawConcentrateKg,
            rawTailingKg: $validResult->rawTailingKg,
            reconciledFeedKg: $validResult->reconciledFeedKg,
            reconciledConcentrateKg: $validResult->reconciledConcentrateKg,
            reconciledTailingKg: $validResult->reconciledTailingKg,
            discrepancyKg: $validResult->discrepancyKg,
            recoveryBps: $validResult->recoveryBps,
            requiresInvestigation: $validResult->requiresInvestigation,
            requiresApproval: $validResult->requiresApproval,
            certificate: $tamperedCert
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Integritas sertifikat neraca massa rusak');

        $verifier->verify($tamperedResult);
    }
}
