<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\GreenProcurementLeaseChoiceService;
use Tests\TestCase;

class GreenProcurementLeaseChoiceTest extends TestCase
{
    use RefreshDatabase;

    protected GreenProcurementLeaseChoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GreenProcurementLeaseChoiceService::class);
    }

    public function test_green_rfq_evaluation_scoring_and_compliance_gate(): void
    {
        // 1. Compliant supplier scored accurately (60% * 80 + 40% * 90 = 48 + 36 = 84.0) (332.1 & 332.4)
        $eval = $this->service->evaluateSupplierRfq(
            evaluationCode: 'EVAL-BIOFUEL-SUPPLIER-01',
            rfqCode: 'RFQ-SMELTER-RENEWABLE-FUEL',
            supplierId: 'SUP-GREEN-ENERGY-CORP',
            passedComplianceGate: true,
            carbonScore: 80.0,
            circularityScore: 90.0,
            isAwarded: true
        );
        $this->assertEquals(84.0, (float) $eval->weighted_green_score);
        $this->assertTrue((bool) $eval->is_awarded);

        // 2. Non-compliant supplier cannot be awarded contract (332.1 & 332.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Green procurement breach: Supplier failing minimum compliance gate cannot be awarded contract');
        $this->service->evaluateSupplierRfq(
            evaluationCode: 'EVAL-FAILED-COMPLIANCE',
            rfqCode: 'RFQ-SMELTER-RENEWABLE-FUEL',
            supplierId: 'SUP-NONCOMPLIANT-OIL',
            passedComplianceGate: false, // Failed!
            carbonScore: 90.0,
            circularityScore: 90.0,
            isAwarded: true // Cannot award!
        );
    }

    public function test_green_lease_incentive_and_tenant_refusal_alternate_tier(): void
    {
        // 1. Cooperative tenant achieving > 15% reduction gets 5% rebate (332.2 & 332.4)
        $lease = $this->service->settleGreenLeaseIncentive(
            leaseCode: 'LEASE-RETAIL-UNIT-101',
            tenantId: 'TENANT-ECO-STORE',
            facilityCode: 'FAC-MALL-SENAYAN',
            tenantAcceptedTarget: true,
            verifiedReductionPct: 18.5,
            baseRentUsd: 10000.0,
            performanceVerified: true
        );
        $this->assertEquals(500.0, (float) $lease->incentive_rebate_usd); // 5% of 10,000
        $this->assertEquals('STANDARD_GREEN_TIER', $lease->service_tier);

        // 2. Tenant refusing green target is gracefully transitioned to alternate conventional tier (332.5 Edge Case)
        $refusedLease = $this->service->settleGreenLeaseIncentive(
            leaseCode: 'LEASE-OFFICE-FLOOR-5',
            tenantId: 'TENANT-CONVENTIONAL-LOGISTICS',
            facilityCode: 'FAC-OFFICE-TOWER-1',
            tenantAcceptedTarget: false, // Refused target!
            verifiedReductionPct: 0.0,
            baseRentUsd: 20000.0,
            performanceVerified: true
        );
        $this->assertEquals(0.00, (float) $refusedLease->incentive_rebate_usd);
        $this->assertEquals('ALTERNATE_CONVENTIONAL_TIER', $refusedLease->service_tier);
    }

    public function test_esg_procurement_lease_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->evaluateSupplierRfq('EVAL-AUD', 'RFQ-AUD', 'S1', true, 80.0, 80.0, true);
        $this->service->settleGreenLeaseIncentive('L-AUD', 'T1', 'F1', true, 20.0, 1000.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unverified lease performance receiving rebates
        DB::table('green_lease_performance_incentives')->insert([
            'lease_code' => 'L-DEFECT-UNVERIFIED-REBATE',
            'tenant_id' => 'T2',
            'facility_code' => 'F1',
            'tenant_accepted_green_target' => true,
            'service_tier' => 'STANDARD_GREEN_TIER',
            'verified_energy_reduction_pct' => 20.0,
            'incentive_rebate_usd' => 500.0,
            'performance_verified' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
