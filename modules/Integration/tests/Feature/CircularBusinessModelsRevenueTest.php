<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CircularBusinessModelsRevenueService;
use Tests\TestCase;

class CircularBusinessModelsRevenueTest extends TestCase
{
    use RefreshDatabase;

    protected CircularBusinessModelsRevenueService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CircularBusinessModelsRevenueService::class);
    }

    public function test_circular_lease_ownership_transition_and_deposit_reconciliation(): void
    {
        // 1. Register circular lease with deposit liability (334.1 & 334.3)
        $lease = $this->service->registerCircularLease(
            contractCode: 'LEASE-SOLAR-INVERTER-01',
            sku: 'SKU-INVERTER-50KW',
            businessModel: 'PRODUCT_AS_A_SERVICE',
            monthlyFeeUsd: 250.0,
            depositUsd: 1000.0,
            isProfitable: true
        );
        $this->assertEquals('COMPANY_OWNED_LEASED', $lease->ownership_state);
        $this->assertEquals(1000.0, (float) $lease->deposit_held_usd);

        // 2. Ownership state transition to refurbishment (334.4)
        $updated = $this->service->transitionOwnershipState(
            contractCode: 'LEASE-SOLAR-INVERTER-01',
            newState: 'RETURNED_FOR_REFURBISHMENT'
        );
        $this->assertEquals('RETURNED_FOR_REFURBISHMENT', $updated->ownership_state);

        // 3. Record material recovery evidence upon disassembly/refurbish (334.3 & 334.4)
        $evidence = $this->service->recordRecoveryEvidence(
            evidenceCode: 'REC-COPPER-WINDING-01',
            contractCode: 'LEASE-SOLAR-INVERTER-01',
            recoveredKg: 18.5,
            verified: true
        );
        $this->assertTrue((bool) $evidence->material_recovery_verified);
    }

    public function test_unprofitable_model_redesign_edge_case(): void
    {
        // Unprofitable circular model rejected without redesign (334.5 Edge Case)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Commercial viability breach: Unprofitable circular model cannot be approved');
        $this->service->registerCircularLease(
            contractCode: 'LEASE-LOSING-VENTURE',
            sku: 'SKU-OLD-PUMP',
            businessModel: 'EQUIPMENT_LEASE',
            monthlyFeeUsd: 10.0,
            depositUsd: 50.0,
            isProfitable: false // Unprofitable!
        );
    }

    public function test_circular_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerCircularLease('L-AUD', 'SKU-1', 'PRODUCT_AS_A_SERVICE', 100.0, 500.0, true);
        $this->service->recordRecoveryEvidence('REC-AUD', 'L-AUD', 10.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unverified material recovery
        DB::table('circular_material_recovery_evidences')->insert([
            'evidence_code' => 'REC-DEFECT-UNVERIFIED',
            'contract_code' => 'L-AUD',
            'recovered_material_kg' => 25.0,
            'material_recovery_verified' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
