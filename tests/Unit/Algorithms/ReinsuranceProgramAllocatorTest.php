<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Reinsurance\ProgramAllocationCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Reinsurance\ProgramAllocationResult;
use Modules\Shared\Domain\Algorithms\Reinsurance\ProgramAllocator;
use Modules\Shared\Domain\Algorithms\Reinsurance\XolLayer;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use Tests\TestCase;

final class ReinsuranceProgramAllocatorTest extends TestCase
{
    public function test_vektor_prompt_program_allocator_dan_sertifikat_valid(): void
    {
        $allocator = new ProgramAllocator;
        $verifier = new ProgramAllocationCertificateVerifier;

        // Vektor 1: Gross 1.500.000.000; QS 30%; L1 400jt xs 300jt; L2 500jt xs 700jt
        $l1 = new XolLayer(code: 'L1', attachmentIdr: 300000000, limitIdr: 400000000, reinstatementRatesBps: [10000], layerPremiumIdr: 40000000);
        $l2 = new XolLayer(code: 'L2', attachmentIdr: 700000000, limitIdr: 500000000, reinstatementRatesBps: [10000], layerPremiumIdr: 50000000);

        $result = $allocator->allocateLoss(
            grossIdr: 1500000000,
            quotaShareBps: 3000,
            layers: [$l1, $l2],
            layerUsedIdr: ['L1' => 0, 'L2' => 0]
        );

        $this->assertSame(450000000, $result->quotaShareCededIdr);
        $this->assertSame(1050000000, $result->netAfterQuotaShareIdr);
        $this->assertSame(400000000, $result->layerCededIdr['L1']);
        $this->assertSame(350000000, $result->layerCededIdr['L2']);
        $this->assertSame(300000000, $result->retainedIdr);
        $this->assertTrue($verifier->verify($result));

        // Reinstatement L2
        $this->assertSame(35000000, $result->reinstatementPremiumIdr['L2']);

        // Vektor 2: Surplus
        // SI 400jt -> retensi 100jt (1/4), surplus 300jt (3/4), fak 0
        $split1 = $allocator->splitSurplus(400000000, 100000000, 5);
        $this->assertSame(['retained_idr' => 100000000, 'surplus_idr' => 300000000, 'facultative_idr' => 0], $split1);

        // SI 800jt -> retensi 100jt (1/8), surplus 500jt (5/8), fak 200jt (1/4)
        $split2 = $allocator->splitSurplus(800000000, 100000000, 5);
        $this->assertSame(['retained_idr' => 100000000, 'surplus_idr' => 500000000, 'facultative_idr' => 200000000], $split2);

        // Premi 10.000.000 pada SI 800jt
        $premiumShares = $allocator->apportionSurplusPremium(10000000, $split2);
        $this->assertSame(1250000, $premiumShares['retained_idr']);
        $this->assertSame(6250000, $premiumShares['surplus_idr']);
        $this->assertSame(2500000, $premiumShares['facultative_idr']);

        // Vektor 3: Stop loss 30% xs 110% LR: earned 10M, incurred 13M -> recoverable 2M
        $sl = $allocator->stopLoss(10000000000, 13000000000, 11000, 3000);
        $this->assertSame(2000000000, $sl);
    }

    public function test_sertifikat_rusak_program_allocator_melempar_exception(): void
    {
        $verifier = new ProgramAllocationCertificateVerifier;
        $l1 = new XolLayer(code: 'L1', attachmentIdr: 300000000, limitIdr: 400000000, reinstatementRatesBps: [10000], layerPremiumIdr: 40000000);

        // Sabotase: retained dibuat tidak seimbang dengan gross
        $corrupt = new ProgramAllocationResult(
            grossIdr: 1000000000,
            quotaShareBps: 2000,
            quotaShareCededIdr: 200000000,
            netAfterQuotaShareIdr: 800000000,
            layerCededIdr: ['L1' => 400000000],
            reinstatementPremiumIdr: ['L1' => 0],
            layerUsedBeforeIdr: ['L1' => 0],
            retainedIdr: 500000000, // 200jt + 400jt + 500jt = 1.100jt != 1.000jt
            layers: [$l1]
        );

        $this->expectException(InvalidCertificateException::class);
        $verifier->verify($corrupt);
    }
}
