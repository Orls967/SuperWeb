<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Utility\MicrogridLoadSheddingCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Utility\MicrogridLoadSheddingKnapsack;
use Modules\Shared\Domain\Algorithms\Utility\MicrogridLoadSheddingResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MicrogridLoadSheddingKnapsackTest extends TestCase
{
    public function test_microgrid_load_shedding_prioritizes_critical_loads(): void
    {
        $solver = new MicrogridLoadSheddingKnapsack;
        $verifier = new MicrogridLoadSheddingCertificateVerifier;

        // Total kapasitas genset darurat pulau = 350 kW
        $availableGenerationKw = 350;

        $feeders = [
            ['id' => 1, 'name' => 'ICU & Ruang Operasi RS', 'tier' => 'TIER_1_CRITICAL', 'priority_rank' => 1, 'required_kw' => 120],
            ['id' => 2, 'name' => 'Data Center & Server Ruang', 'tier' => 'TIER_1_CRITICAL', 'priority_rank' => 2, 'required_kw' => 100],
            ['id' => 3, 'name' => 'Cold Storage Logistik Makanan', 'tier' => 'TIER_2_ESSENTIAL', 'priority_rank' => 3, 'required_kw' => 80],
            ['id' => 4, 'name' => 'Penerangan Koridor Mall', 'tier' => 'TIER_3_COMMERCIAL', 'priority_rank' => 4, 'required_kw' => 90],
            ['id' => 5, 'name' => 'Pengisi Daya Mobil Listrik (EV)', 'tier' => 'TIER_4_FLEXIBLE', 'priority_rank' => 5, 'required_kw' => 60],
        ];

        $result = $solver->solve($availableGenerationKw, $feeders);

        $this->assertSame(350, $result->availableGenerationKw);
        $this->assertLessThanOrEqual(350, $result->totalServedKw);
        $this->assertSame(300, $result->totalServedKw); // 120 + 100 + 80 = 300 kW
        $this->assertSame(150, $result->totalShedKw);   // 90 + 60 = 150 kW

        // Verifikasi bahwa ICU (id 1) dan Data Center (id 2) dan Cold Storage (id 3) dipertahankan
        $retainedIds = array_column($result->retainedFeeders, 'id');
        $this->assertContains(1, $retainedIds);
        $this->assertContains(2, $retainedIds);
        $this->assertContains(3, $retainedIds);

        // EV dan Mall HVAC dilepas
        $shedIds = array_column($result->shedFeeders, 'id');
        $this->assertContains(4, $shedIds);
        $this->assertContains(5, $shedIds);

        $this->assertTrue($verifier->verify($result));
    }

    public function test_tampered_load_shedding_fails(): void
    {
        $solver = new MicrogridLoadSheddingKnapsack;
        $verifier = new MicrogridLoadSheddingCertificateVerifier;

        $result = $solver->solve(200, [
            ['id' => 1, 'name' => 'Critical', 'tier' => 'TIER_1_CRITICAL', 'priority_rank' => 1, 'required_kw' => 100],
        ]);

        $tampered = new MicrogridLoadSheddingResult(
            availableGenerationKw: $result->availableGenerationKw,
            totalServedKw: $result->totalServedKw,
            totalShedKw: $result->totalShedKw,
            totalUtilityScore: $result->totalUtilityScore,
            retainedFeeders: $result->retainedFeeders,
            shedFeeders: $result->shedFeeders,
            certificateHash: 'corrupted-hash',
            canonicalPayload: $result->canonicalPayload
        );

        $this->expectException(RuntimeException::class);
        $verifier->verify($tampered);
    }
}
