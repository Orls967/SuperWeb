<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Csp\ArcConsistency;
use Modules\Shared\Domain\Algorithms\Csp\ArcConsistencyCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Csp\ArcConsistencyResult;
use Modules\Shared\Domain\Algorithms\Csp\Constraint;
use Modules\Shared\Domain\Algorithms\Csp\QuickXplain;
use Modules\Shared\Domain\Algorithms\Csp\QuickXplainCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Csp\QuickXplainResult;
use Tests\TestCase;

class CspAlgorithmTest extends TestCase
{
    /** @return list<Constraint> */
    private function getTableTemplateConstraints(): array
    {
        return [
            // c1 W >= D+20
            new Constraint('W', 'D', fn ($w, $d) => $w >= $d + 20, 'c1_W_ge_D_plus_20'),
            // c2 M=kaca -> W <= 100
            new Constraint('M', 'W', fn ($m, $w) => $m !== 'kaca' || $w <= 100, 'c2_kaca_W_le_100'),
            // c3 L=3 -> W <= 80
            new Constraint('L', 'W', fn ($l, $w) => $l !== 3 || $w <= 80, 'c3_L3_W_le_80'),
            // c4 M=besi -> L != 3
            new Constraint('M', 'L', fn ($m, $l) => $m !== 'besi' || $l !== 3, 'c4_besi_L_ne_3'),
            // c5 W >= 100 -> L >= 4
            new Constraint('W', 'L', fn ($w, $l) => $w < 100 || $l >= 4, 'c5_W100_L_ge_4'),
        ];
    }

    /** U-14 & D82-2: Vektor uji AC-3 dan QuickXplain konfigurator C2M */
    public function test_csp_arc_consistency_and_quickxplain_prompt_vectors(): void
    {
        $ac3 = new ArcConsistency;
        $constraints = $this->getTableTemplateConstraints();

        $initialDomains = [
            'W' => [60, 80, 100, 120],
            'D' => [40, 60, 80],
            'M' => ['kayu', 'besi', 'kaca'],
            'L' => [3, 4, 6],
        ];

        // 1. AC-3 dengan W=80, M=besi -> D {40,60}, L {4,6}
        $dom1 = $initialDomains;
        $dom1['W'] = [80];
        $dom1['M'] = ['besi'];
        $res1 = $ac3->enforce($dom1, $constraints);

        $this->assertTrue($res1->isConsistent);
        $this->assertEquals([40, 60], $res1->domains['D']);
        $this->assertEquals([4, 6], $res1->domains['L']);

        // 2. AC-3 dengan W=120 -> M {kayu,besi}, L {4,6}, D {40,60,80}
        $dom2 = $initialDomains;
        $dom2['W'] = [120];
        $res2 = $ac3->enforce($dom2, $constraints);

        $this->assertTrue($res2->isConsistent);
        $this->assertEquals(['kayu', 'besi'], $res2->domains['M']);
        $this->assertEquals([4, 6], $res2->domains['L']);
        $this->assertEquals([40, 60, 80], $res2->domains['D']);

        // 3. QuickXplain: pilihan (W=120, D=80, M=kaca, L=3) -> konflik minimal [W=120, M=kaca]
        $qx = new QuickXplain;
        $choice1 = ['W' => 120, 'D' => 80, 'M' => 'kaca', 'L' => 3];
        $qxRes1 = $qx->explain($constraints, $choice1);

        $this->assertEquals(['W' => 120, 'M' => 'kaca'], $qxRes1->minimalConflict);

        // 4. QuickXplain: pilihan (W=60, D=60, M=kayu, L=4) -> konflik minimal [W=60, D=60]
        $choice2 = ['W' => 60, 'D' => 60, 'M' => 'kayu', 'L' => 4];
        $qxRes2 = $qx->explain($constraints, $choice2);

        $this->assertEquals(['W' => 60, 'D' => 60], $qxRes2->minimalConflict);
    }

    /** PR2 & U-14: Sertifikat AC-3 / QuickXplain rusak melempar exception */
    public function test_csp_corrupted_certificate_throws_exception(): void
    {
        $constraints = $this->getTableTemplateConstraints();
        $initialDomains = [
            'W' => [80],
            'D' => [40, 60, 80],
            'M' => ['besi'],
            'L' => [3, 4, 6],
        ];

        // Tampered AC-3 result
        $corruptAc3 = new ArcConsistencyResult(
            domains: ['W' => [80], 'D' => [40, 60], 'M' => ['besi'], 'L' => [4, 6]],
            isConsistent: true,
            certificate: 'tampered-ac3-hash'
        );

        $this->expectException(InvalidArgumentException::class);
        ArcConsistencyCertificateVerifier::verify($initialDomains, $constraints, $corruptAc3);
    }

    public function test_quickxplain_corrupted_certificate_throws_exception(): void
    {
        $constraints = $this->getTableTemplateConstraints();
        $choice = ['W' => 120, 'D' => 80, 'M' => 'kaca', 'L' => 3];

        $corruptQx = new QuickXplainResult(
            minimalConflict: ['W' => 120, 'M' => 'kaca'],
            certificate: 'tampered-qx-hash'
        );

        $this->expectException(InvalidArgumentException::class);
        QuickXplainCertificateVerifier::verify($constraints, $choice, $corruptQx);
    }
}
