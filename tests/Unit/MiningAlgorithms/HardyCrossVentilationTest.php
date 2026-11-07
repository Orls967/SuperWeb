<?php

declare(strict_types=1);

namespace Tests\Unit\MiningAlgorithms;

use Modules\Shared\Domain\Algorithms\Underground\AirwayBranch;
use Modules\Shared\Domain\Algorithms\Underground\HardyCrossVentilationNetworkSolver;
use Modules\Shared\Domain\Algorithms\Underground\HardyCrossVentilationNetworkVerifier;
use Modules\Shared\Domain\Algorithms\Underground\VentilationLoop;
use Tests\TestCase;

class HardyCrossVentilationTest extends TestCase
{
    public function test_hardy_cross_solver_converges_on_standard_mine_circuit(): void
    {
        $solver = new HardyCrossVentilationNetworkSolver;

        $branches = [
            new AirwayBranch(
                branchId: 'B1_INTAKE',
                fromNode: 'SURFACE',
                toNode: 'JUNCTION_1',
                resistanceR: 0.040,
                initialFlowQ: 100.0,
                fanPressurePa: 1200.0,
                isFanBranch: true,
                lengthM: 300.0,
                areaM2: 18.0
            ),
            new AirwayBranch(
                branchId: 'B2_DRIFT_A',
                fromNode: 'JUNCTION_1',
                toNode: 'JUNCTION_2',
                resistanceR: 0.080,
                initialFlowQ: 50.0,
                fanPressurePa: 0.0,
                isFanBranch: false,
                lengthM: 200.0,
                areaM2: 14.0
            ),
            new AirwayBranch(
                branchId: 'B3_DRIFT_B',
                fromNode: 'JUNCTION_1',
                toNode: 'JUNCTION_2',
                resistanceR: 0.080,
                initialFlowQ: 50.0,
                fanPressurePa: 0.0,
                isFanBranch: false,
                lengthM: 200.0,
                areaM2: 14.0
            ),
            new AirwayBranch(
                branchId: 'B4_RETURN',
                fromNode: 'JUNCTION_2',
                toNode: 'SURFACE_EXHAUST',
                resistanceR: 0.045,
                initialFlowQ: 100.0,
                fanPressurePa: 0.0,
                isFanBranch: false,
                lengthM: 320.0,
                areaM2: 18.0
            ),
        ];

        $loops = [
            new VentilationLoop(
                loopId: 'LOOP_PARALLEL_SPLIT',
                branches: [
                    ['branch_id' => 'B2_DRIFT_A', 'direction' => 1],
                    ['branch_id' => 'B3_DRIFT_B', 'direction' => -1],
                ]
            ),
        ];

        $result = $solver->solve($branches, $loops, tolerance: 0.001, maxIterations: 50);

        $this->assertTrue($result->converged, 'Solver Hardy-Cross harus konvergen.');
        $this->assertLessThanOrEqual(50, $result->iterationsCount);
        $this->assertNotEmpty($result->hmacSignature);

        // Dua lintasan paralel dengan resistansi identik harus membagi debit secara seimbang
        $qA = $result->finalFlowsQ['B2_DRIFT_A'];
        $qB = $result->finalFlowsQ['B3_DRIFT_B'];
        $this->assertEqualsWithDelta($qA, $qB, 0.01, 'Aliran pada dua cabang simetris harus seimbang.');
    }

    public function test_hardy_cross_verifier_certifies_vector(): void
    {
        $solver = new HardyCrossVentilationNetworkSolver;
        $branches = [
            new AirwayBranch(
                branchId: 'B1_IN',
                fromNode: 'N1',
                toNode: 'N2',
                resistanceR: 0.040,
                initialFlowQ: 80.0,
                fanPressurePa: 1000.0,
                isFanBranch: true
            ),
            new AirwayBranch(
                branchId: 'B2_OUT',
                fromNode: 'N2',
                toNode: 'N1',
                resistanceR: 0.050,
                initialFlowQ: 80.0
            ),
        ];
        $loops = [
            new VentilationLoop(
                loopId: 'LOOP_1',
                branches: [
                    ['branch_id' => 'B1_IN', 'direction' => 1],
                    ['branch_id' => 'B2_OUT', 'direction' => 1],
                ]
            ),
        ];
        $result = $solver->solve($branches, $loops);

        $isCertified = HardyCrossVentilationNetworkVerifier::verify($result);
        $this->assertTrue($isCertified, 'Verifier resmi M7 harus menyatakan tersertifikasi penuh.');
    }
}
