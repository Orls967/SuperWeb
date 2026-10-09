<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ColdChainHighValueLogisticsService;
use Tests\TestCase;

class ColdChainHighValueLogisticsTest extends TestCase
{
    use RefreshDatabase;

    protected ColdChainHighValueLogisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ColdChainHighValueLogisticsService::class);
    }

    public function test_cold_chain_excursion_quarantines_cargo_and_triggers_insurance_claim(): void
    {
        // 1. Normal temperature within 2°C - 8°C (303.1 & 303.4)
        $normal = $this->service->monitorColdChainShipment('SHIP-VACCINE-01', 'VACCINE', 4.5, 2.0, 8.0);
        $this->assertFalse((bool) $normal->is_excursion_detected);
        $this->assertEquals('NORMAL_TRANSIT', $normal->disposition_status);
        $this->assertFalse((bool) $normal->insurance_claim_triggered);

        // 2. Temperature spike (14.2°C) automatically quarantines cargo and triggers claim (303.4 & 303.5 Edge Case)
        $excursion = $this->service->monitorColdChainShipment('SHIP-INSULIN-02', 'BIOLOGIC_INSULIN', 14.2, 2.0, 8.0);
        $this->assertTrue((bool) $excursion->is_excursion_detected);
        $this->assertEquals('QUARANTINED', $excursion->disposition_status);
        $this->assertTrue((bool) $excursion->insurance_claim_triggered);
    }

    public function test_high_value_security_dual_control_and_route_risk_reassessment(): void
    {
        // 1. Consignment value >= $50,000 without dual control is rejected (303.3 & 303.4)
        try {
            $this->service->dispatchHighValueConsignment(
                consignmentCode: 'HV-GOLD-BULLION-01',
                declaredValueUsd: 150000.0,
                routeRiskLevel: 'LOW',
                primaryAgentId: 'GUARD_AGUS',
                secondaryAgentId: null
            );
            $this->fail('Expected exception for missing dual control');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('strictly require dual control custody agents', $e->getMessage());
        }

        // 2. High risk corridor transit without reassessment is rejected (303.6 Risk Guardrail)
        try {
            $this->service->dispatchHighValueConsignment(
                consignmentCode: 'HV-CASH-SURABAYA-02',
                declaredValueUsd: 200000.0,
                routeRiskLevel: 'HIGH_RISK_ZONE',
                primaryAgentId: 'GUARD_AGUS',
                secondaryAgentId: 'GUARD_BUDI',
                riskReassessmentCompleted: false
            );
            $this->fail('Expected exception for missing route risk reassessment');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires mandatory route risk reassessment', $e->getMessage());
        }

        // 3. Fully validated high-value transit succeeds (303.3 & 303.4)
        $dispatch = $this->service->dispatchHighValueConsignment(
            consignmentCode: 'HV-CASH-SURABAYA-03',
            declaredValueUsd: 200000.0,
            routeRiskLevel: 'HIGH_RISK_ZONE',
            primaryAgentId: 'GUARD_AGUS',
            secondaryAgentId: 'GUARD_BUDI',
            riskReassessmentCompleted: true
        );
        $this->assertTrue((bool) $dispatch->dual_control_verified);
        $this->assertTrue((bool) $dispatch->route_risk_reassessment_completed);
    }

    public function test_logistics_cold_chain_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->monitorColdChainShipment('SHIP-AUD', 'CARGO', 5.0);
        $this->service->dispatchHighValueConsignment('HV-AUD', 60000.0, 'LOW', 'G1', 'G2');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unquarantined excursion
        DB::table('logistics_cold_chain_excursions')->insert([
            'shipment_code' => 'SHIP-UNQUARANTINED',
            'cargo_type' => 'PHARMA',
            'min_allowed_temp_c' => 2.0,
            'max_allowed_temp_c' => 8.0,
            'recorded_temp_c' => 22.0,
            'is_excursion_detected' => true,
            'disposition_status' => 'NORMAL_TRANSIT', // Discrepancy!
            'insurance_claim_triggered' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
