<?php

declare(strict_types=1);

namespace Tests\Acceptance\Boundaries;

use InvalidArgumentException;
use Modules\Core\Domain\Algorithms\Boundaries\ContractCoverageScorer;
use Modules\Core\Domain\Algorithms\Boundaries\CoverageCertificateVerifier;
use Modules\Core\Domain\Algorithms\Boundaries\PrefixCertificateVerifier;
use Modules\Core\Domain\Algorithms\Boundaries\SccCertificateVerifier;
use Modules\Core\Domain\Algorithms\Boundaries\StronglyConnectedComponents;
use Modules\Core\Domain\Algorithms\Boundaries\TablePrefixOwnershipResolver;
use PHPUnit\Framework\TestCase;

/**
 * Verifikasi vektor algoritma exact & sertifikat SHA-256 Fase R4 (U-14, T-10).
 */
class BoundaryAlgorithmsTest extends TestCase
{
    public function test_algorithm_a1_strongly_connected_components_exact_vectors(): void
    {
        $scc = new StronglyConnectedComponents;

        // Vektor 1: sisi A->B, B->C, C->A, C->D, D->E, E->F, F->D, F->G => [[A,B,C],[D,E,F]]
        $nodes = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        $adj = [
            'A' => ['B'],
            'B' => ['C'],
            'C' => ['A', 'D'],
            'D' => ['E'],
            'E' => ['F'],
            'F' => ['D', 'G'],
            'G' => [],
        ];

        $res = $scc->compute($nodes, $adj);
        $this->assertTrue($res['hasCycles']);
        $this->assertSame([['A', 'B', 'C'], ['D', 'E', 'F']], $res['cycles']);

        // Vektor 2: Graf asiklik A->B, B->C => []
        $acyclic = $scc->compute(['A', 'B', 'C'], ['A' => ['B'], 'B' => ['C'], 'C' => []]);
        $this->assertFalse($acyclic['hasCycles']);
        $this->assertSame([], $acyclic['cycles']);

        // Vektor 3: Self-loop A->A saja => [] (self-loop diabaikan per W8-D41)
        $selfLoop = $scc->compute(['A'], ['A' => ['A']]);
        $this->assertFalse($selfLoop['hasCycles']);
        $this->assertSame([], $selfLoop['cycles']);

        // Sertifikat sah -> lolos, sertifikat rusak -> exception
        $verifier = new SccCertificateVerifier;
        $cert = $verifier->issueCertificate(['nodes' => $nodes], ['Tarjan DFS deterministik'], $res);
        $this->assertTrue($verifier->verifyCertificate($cert));

        $tampered = $cert;
        $tampered['result']['cycles'] = [];
        $this->expectException(InvalidArgumentException::class);
        $verifier->verifyCertificate($tampered);
    }

    public function test_algorithm_a2_table_prefix_ownership_exact_vectors(): void
    {
        $map = [
            'hcm_' => 'Hcm',
            'hcm_pay_' => 'Payroll',
            'ins_' => 'Insurance',
            'pric_' => 'Pricing',
        ];
        $resolver = new TablePrefixOwnershipResolver($map);

        $this->assertSame('Hcm', $resolver->resolveOwnershipLabel('hcm_employees'));
        $this->assertSame('Payroll', $resolver->resolveOwnershipLabel('hcm_pay_runs'));
        $this->assertSame('Hcm', $resolver->resolveOwnershipLabel('hcm_payroll'));
        $this->assertSame('Insurance', $resolver->resolveOwnershipLabel('ins_claims'));
        $this->assertSame('UNOWNED', $resolver->resolveOwnershipLabel('foo_bar'));

        // Konflik prefiks identik -> PREFIX_CONFLICT [ins_]
        $conflicts = TablePrefixOwnershipResolver::detectConflicts([
            ['prefix' => 'ins_', 'owner' => 'Insurance'],
            ['prefix' => 'ins_', 'owner' => 'Health'],
        ]);
        $this->assertSame(['ins_'], $conflicts);

        // Sertifikat sah -> lolos, sertifikat rusak -> exception
        $verifier = new PrefixCertificateVerifier;
        $cert = $verifier->issueCertificate(
            ['prefixes' => $map],
            ['Longest prefix match'],
            ['hcm_pay_runs' => 'Payroll', 'hcm_payroll' => 'Hcm']
        );
        $this->assertTrue($verifier->verifyCertificate($cert));

        $tampered = $cert;
        $tampered['sha256'] = str_repeat('0', 64);
        $this->expectException(InvalidArgumentException::class);
        $verifier->verifyCertificate($tampered);
    }

    public function test_algorithm_a3_contract_coverage_scorer_exact_vectors(): void
    {
        $scorer = new ContractCoverageScorer;

        // Vektor: X(18,6)->750; Y(1,2)->333; Z(0,0)->1000; W(5,5)->500; V(5,0)->1000
        // Peringkat: Y 333, W 500, X 750, V 1000, Z 1000
        $ranked = $scorer->rankModules([
            'X' => ['via_kontrak' => 18, 'langsung' => 6],
            'Y' => ['via_kontrak' => 1, 'langsung' => 2],
            'Z' => ['via_kontrak' => 0, 'langsung' => 0],
            'W' => ['via_kontrak' => 5, 'langsung' => 5],
            'V' => ['via_kontrak' => 5, 'langsung' => 0],
        ]);

        $this->assertSame([
            ['module' => 'Y', 'via_kontrak' => 1, 'langsung' => 2, 'score' => 333],
            ['module' => 'W', 'via_kontrak' => 5, 'langsung' => 5, 'score' => 500],
            ['module' => 'X', 'via_kontrak' => 18, 'langsung' => 6, 'score' => 750],
            ['module' => 'V', 'via_kontrak' => 5, 'langsung' => 0, 'score' => 1000],
            ['module' => 'Z', 'via_kontrak' => 0, 'langsung' => 0, 'score' => 1000],
        ], $ranked);

        $aggregate = $scorer->calculate(['X:Core' => 18, 'Y:Core' => 1], [['client' => 'X', 'owner' => 'Core']]);
        $verifier = new CoverageCertificateVerifier;
        $cert = $verifier->issueCertificate(['modules' => 2], ['Integer intdiv permille'], $aggregate);
        $this->assertTrue($verifier->verifyCertificate($cert));

        $tampered = $cert;
        $tampered['result']['globalScore'] = 9999;
        $this->expectException(InvalidArgumentException::class);
        $verifier->verifyCertificate($tampered);
    }
}
