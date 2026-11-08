<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Application\Services\AquaculturePondRestingCycleService;
use Modules\Marine\Application\Services\MarineAquacultureLedgerReconciliationService;
use Modules\Marine\Domain\Models\AquacultureBatch;
use Modules\Marine\Domain\StateMachines\AquacultureBatchStateMachine;
use Tests\TestCase;

class PondFallowingAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_fallowing_and_pond_liming_sterilization_flow(): void
    {
        $service = new AquaculturePondRestingCycleService(new AquacultureBatchStateMachine);
        $batch = AquacultureBatch::first();

        // 1. Inisiasi fallowing
        $batchFallowed = $service->initiateFallowingPeriod($batch->id, '2026-11-01');
        $this->assertEquals(AquacultureBatchStateMachine::STATUS_FALLOWING, $batchFallowed->stage);

        // 2. Verifikasi pengapuran dan sanitasi
        $limingAudit = [
            'lime_type' => 'AGRICULTURAL_CALCIUM_CARBONATE',
            'applied_dosage_kg' => 600.0,
            'pond_area_hectares' => 0.5, // 1200 kg/ha >= 1000 kg/ha
            'soil_ph_before' => 5.2,
            'soil_ph_after' => 7.4,
            'fallowing_duration_days' => 16, // >= 14 hari
        ];

        $res = $service->verifyPondLimingAndSterilization($batch->id, $limingAudit);

        $this->assertTrue($res['is_sterilized_and_ready']);
        $this->assertEquals(1200.0, $res['dosage_kg_per_ha']);
        $this->assertEquals('POND_SANITATION_PASSED', $res['sanitation_certificate']);
        $this->assertEquals(AquacultureBatchStateMachine::STATUS_POND_STERILIZED, $batch->fresh()->stage);
    }

    public function test_financial_and_physical_biomass_reconciliation(): void
    {
        $reconService = new MarineAquacultureLedgerReconciliationService;

        // 1. Financial Ledger Reconciliation
        $fin = $reconService->reconcileOrderLedgerEntries();
        $this->assertTrue($fin['is_fully_reconciled']);
        $this->assertEquals(0, $fin['discrepancy_amount_minor_cents']);
        $this->assertEquals('RECONCILIATION_MATCH_PERFECT', $fin['audit_verdict']);

        // 2. Physical Biomass Reconciliation
        $phys = $reconService->reconcilePhysicalBiomassBalance();
        $this->assertTrue($phys['is_within_biological_limits']);
        $this->assertEquals(3300.0, $phys['total_harvest_biomass_kg']);
        $this->assertEquals(2178.0, $phys['total_export_lots_net_weight_kg']);
        $this->assertEquals(66.0, $phys['aggregate_conversion_yield_percentage']);
    }
}
