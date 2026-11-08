<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Application\Sagas\AquacultureExportExecutionSaga;
use Modules\Marine\Domain\Algorithms\ExportAssayToleranceChecker;
use Modules\Marine\Domain\Algorithms\SeafoodLotMerkleProofGenerator;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\StateMachines\ExportShipmentStateMachine;
use Tests\TestCase;

class AquacultureExportExecutionSagaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_saga_execution_fails_and_compensates_when_assay_fails(): void
    {
        $saga = new AquacultureExportExecutionSaga(
            new ExportAssayToleranceChecker,
            new SeafoodLotMerkleProofGenerator,
            new ExportShipmentStateMachine
        );

        $lot = AquacultureExportLot::first();
        $this->assertNotNull($lot);

        $taintedAssay = [
            'chloramphenicol_ppb' => 2.5, // Gagal batas
            'salmonella_present' => true,
        ];

        $res = $saga->execute(
            'ORD-TEST-FAIL-01',
            $lot,
            $taintedAssay,
            [['event' => '01_ORIGIN']],
            'US_FDA',
            1500000,
            'MSKU1122334'
        );

        $this->assertFalse($res['success']);
        $this->assertEquals('ABORTED_AND_COMPENSATED', $res['current_step']);
        $this->assertNotNull($res['error_message']);
    }
}
