<?php

declare(strict_types=1);

namespace Tests\Acceptance\Intercompany;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Algorithms\BilateralEliminationMatcher;
use Modules\Intercompany\Domain\Algorithms\Certificates\BilateralEliminationCertificateVerifier;
use Modules\Intercompany\Domain\DTOs\BilateralBalanceDTO;
use Modules\Intercompany\Domain\Exceptions\InvalidCertificateException;
use Tests\TestCase;

class BilateralEliminationAlgorithmVectorTest extends TestCase
{
    public function test_bilateral_elimination_matcher_matches_all_prompt_vectors(): void
    {
        $matcher = new BilateralEliminationMatcher;

        // Vektor 1: Pasangan Tepat Sama (Exact Match Delta = 0)
        // Penjual HOLDING, Pembeli SUB_A: AR = 10.000.000, AP = 10.000.000
        $pair1 = new BilateralBalanceDTO(
            sellerEntity: 'HOLDING',
            buyerEntity: 'SUB_A',
            receivableAmountIdr: 10_000_000,
            payableAmountIdr: 10_000_000,
            salesRevenueIdr: 10_000_000,
            cogsExpenseIdr: 10_000_000
        );

        $res1 = $matcher->matchPairs(['2026-10'], [$pair1]);
        $this->assertSame(1, $res1->matchedPairCount);
        $this->assertSame(10_000_000, $res1->totalEliminatedBalanceIdr);
        $this->assertSame(10_000_000, $res1->totalEliminatedPnlIdr);
        $this->assertSame(0, $res1->totalDiscrepancyIdr);
        $this->assertSame('MATCHED_EXACT', $res1->results[0]->status);
        $this->assertTrue(BilateralEliminationCertificateVerifier::verify($res1->certificate));

        // Vektor 2: Barang Dalam Perjalanan (In-Transit Difference)
        // AR = 14.000.000, AP = 10.000.000 -> Matched = 10.000.000, In-Transit = 4.000.000
        $pair2 = new BilateralBalanceDTO(
            sellerEntity: 'SUB_A',
            buyerEntity: 'SUB_B',
            receivableAmountIdr: 14_000_000,
            payableAmountIdr: 10_000_000,
            salesRevenueIdr: 14_000_000,
            cogsExpenseIdr: 10_000_000,
            inTransitAmountIdr: 4_000_000
        );

        $res2 = $matcher->matchPairs(['2026-10'], [$pair2]);
        $this->assertSame(10_000_000, $res2->totalEliminatedBalanceIdr);
        $this->assertSame(4_000_000, $res2->totalInTransitIdr);
        $this->assertSame('IN_TRANSIT_DETECTED', $res2->results[0]->status);
        $this->assertTrue(BilateralEliminationCertificateVerifier::verify($res2->certificate));

        // Vektor 3: Multi-Entitas Bilateral Matrix (3 Pasang Entitas)
        // HOLDING -> SUB_A: 15.000.000
        // SUB_A -> SUB_B: 7.500.000
        // SUB_B -> HOLDING: 3.200.000
        // Total Eliminasi Grup = 25.700.000 IDR
        $pair3a = new BilateralBalanceDTO('HOLDING', 'SUB_A', 15_000_000, 15_000_000, 15_000_000, 15_000_000);
        $pair3b = new BilateralBalanceDTO('SUB_A', 'SUB_B', 7_500_000, 7_500_000, 7_500_000, 7_500_000);
        $pair3c = new BilateralBalanceDTO('SUB_B', 'HOLDING', 3_200_000, 3_200_000, 3_200_000, 3_200_000);

        $res3 = $matcher->matchPairs(['2026-10'], [$pair3a, $pair3b, $pair3c]);
        $this->assertSame(3, $res3->matchedPairCount);
        $this->assertSame(25_700_000, $res3->totalEliminatedBalanceIdr);
        $this->assertSame(0, $res3->totalDiscrepancyIdr);
        $this->assertTrue(BilateralEliminationCertificateVerifier::verify($res3->certificate));

        // Vektor 4: Identical entities rejection
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IDENTICAL_ENTITIES_NOT_ALLOWED');
        new BilateralBalanceDTO('HOLDING', 'HOLDING', 1000, 1000, 1000, 1000);
    }

    public function test_corrupted_bilateral_elimination_certificate_throws_exception(): void
    {
        $matcher = new BilateralEliminationMatcher;
        $pair = new BilateralBalanceDTO('HOLDING', 'SUB_A', 10_000_000, 10_000_000, 10_000_000, 10_000_000);
        $res = $matcher->matchPairs(['2026-10'], [$pair]);

        $corrupt = $res->certificate;
        $corrupt->sha256 = 'corrupted_elimination_hash';

        $this->expectException(InvalidCertificateException::class);
        BilateralEliminationCertificateVerifier::assertValid($corrupt);
    }
}
