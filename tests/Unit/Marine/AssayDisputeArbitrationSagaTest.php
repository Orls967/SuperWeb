<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Application\Sagas\AssayDisputeArbitrationSaga;
use Modules\Marine\Domain\Algorithms\ExportAssayToleranceChecker;
use Modules\Marine\Domain\Models\AquacultureEscrowSettlement;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Tests\TestCase;

class AssayDisputeArbitrationSagaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_arbitration_saga_releases_escrow_to_seller_when_reference_lab_proves_clean(): void
    {
        $saga = new AssayDisputeArbitrationSaga(new ExportAssayToleranceChecker);

        $lot = AquacultureExportLot::first();
        $escrow = AquacultureEscrowSettlement::first();

        $cleanLabResults = [
            'chloramphenicol_ppb' => 0.0,
            'nitrofuran_aoz_ppb' => 0.0,
            'histamine_ppm' => 3.5,
            'salmonella_present' => false,
        ];

        $res = $saga->arbitrate('DISPUTE-2026-001', $lot, $escrow, 'US_FDA', $cleanLabResults);

        $this->assertEquals('BUYER_CLAIM_DISMISSED_SELLER_PAID', $res['verdict']);
        $this->assertEquals(0, $res['escrow_refunded_minor_cents']);
        $this->assertGreaterThan(0, $res['escrow_released_to_seller_minor_cents']);
        $this->assertEquals('RELEASED', $escrow->fresh()->status);
    }

    public function test_arbitration_saga_refunds_buyer_when_reference_lab_confirms_contamination(): void
    {
        $saga = new AssayDisputeArbitrationSaga(new ExportAssayToleranceChecker);

        $lot = AquacultureExportLot::first();
        $escrow = AquacultureEscrowSettlement::first();

        $contaminatedLabResults = [
            'chloramphenicol_ppb' => 1.2, // Melebihi batas MRPL
            'salmonella_present' => false,
        ];

        $res = $saga->arbitrate('DISPUTE-2026-002', $lot, $escrow, 'EU', $contaminatedLabResults);

        $this->assertEquals('BUYER_CLAIM_UPHELD_ESCROW_REFUNDED', $res['verdict']);
        $this->assertGreaterThan(0, $res['escrow_refunded_minor_cents']);
        $this->assertEquals(0, $res['escrow_released_to_seller_minor_cents']);
        $this->assertEquals('REFUNDED', $escrow->fresh()->status);
    }
}
