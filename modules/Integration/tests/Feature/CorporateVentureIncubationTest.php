<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\CorporateVentureIncubationService;
use Tests\TestCase;

/**
 * Fase 234 — Inovasi: Corporate Venture, Incubation & Acceleration Tests
 *
 * Covers:
 *  (a) Staged funding bounded strictly by approved ceiling
 *  (b) Edge Case 234.7: Stage-gate tranche disbursement blocked until milestone is achieved and audited
 *  (c) Shared incubation platform asset usage metering
 *  (d) Edge Case 234.6: Internal competitor non-compete enforcement revoking data access
 *  (e) Kill decision immediately cutting off platform and data access
 *  (f) Quality audit ppm:audit clean with 0 discrepancies
 */
class CorporateVentureIncubationTest extends TestCase
{
    use RefreshDatabase;

    protected CorporateVentureIncubationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CorporateVentureIncubationService::class);
    }

    /**
     * (a) & (b) Staged funding ceilings and milestone stage-gate audit enforcement (234.1, 234.5 & 234.7).
     */
    public function test_stage_gate_funding_and_milestone_audit(): void
    {
        // 1. Create venture with 10B IDR approved ceiling
        $venture = $this->service->createVenture(
            'VTR-AGRI-AI',
            'AgriTech Drone AI',
            'INCUBATE',
            25000000000,
            10000000000
        );

        // 2. Tranche 1: 3B Seed
        $t1 = $this->service->createFundingTranche($venture->venture_code, 'SEED', 3000000000, 'Deliver MVP drone pilot');

        // 3. Attempting to create tranche exceeding remaining (e.g. 8B > 7B remaining) -> Exception
        try {
            $this->service->createFundingTranche($venture->venture_code, 'SERIES_A', 8000000000, 'Scale nationwide');
            $this->fail('Expected exception for exceeding approved funding ceiling.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('funding ceiling exceeded', $e->getMessage());
        }

        // 4. Attempting to disburse tranche 1 before milestone audit -> Exception (234.7)
        try {
            $this->service->disburseTranche($t1->tranche_code);
            $this->fail('Expected exception for disbursing unaudited milestone.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cannot be disbursed until required milestone is achieved and audited', $e->getMessage());
        }

        // 5. Verify & audit milestone -> Disburse succeeds
        $this->service->verifyAndAuditMilestone($t1->tranche_code);
        $disbursed = $this->service->disburseTranche($t1->tranche_code);
        $this->assertTrue((bool) $disbursed->disbursed);
    }

    /**
     * (c) Shared incubation platform asset usage metering (234.2).
     */
    public function test_incubation_platform_asset_metering(): void
    {
        $venture = $this->service->createVenture(
            'VTR-FIN-PAY',
            'Cross-border Remittance Sim',
            'JOINT_VENTURE',
            15000000000,
            5000000000
        );

        // Record usage of Payment Gateway API: 1,000 transactions at 500 IDR rate
        $usage = $this->service->recordPlatformAssetUsage($venture->venture_code, 'PAYMENT_GATEWAY', 1000, 500);
        $this->assertEquals(500000.0, (float) $usage->total_shared_cost);
    }

    /**
     * (d) Edge Case 234.6: Internal competitor non-compete data isolation.
     */
    public function test_internal_competitor_data_isolation(): void
    {
        $venture = $this->service->createVenture(
            'VTR-RIVAL-ISP',
            'Rival Fiber Operator',
            'BUILD_IN_HOUSE',
            8000000000,
            3000000000
        );

        // Conflict detected: Venture pivots into direct competition with internal ISP division
        $isolated = $this->service->isolateInternalCompetitor($venture->venture_code, 'Product overlap with Core Telco Fiber business');
        $this->assertTrue((bool) $isolated->is_internal_competitor);
        $this->assertTrue((bool) $isolated->non_compete_isolated);
        $this->assertFalse((bool) $isolated->shared_platform_access_active);

        // Subsequent attempt to access shared platform assets -> Throws exception
        try {
            $this->service->recordPlatformAssetUsage($venture->venture_code, 'DATA_API', 500, 100);
            $this->fail('Expected exception for data access on isolated competitor.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('data-isolated or terminated', $e->getMessage());
        }
    }

    /**
     * (e) Kill decision revokes data access (234.5).
     */
    public function test_kill_decision_cuts_data_access(): void
    {
        $venture = $this->service->createVenture(
            'VTR-FAIL-META',
            'VR Retail Mall',
            'INCUBATE',
            5000000000,
            2000000000
        );

        $killed = $this->service->executeKillDecision($venture->venture_code, 'Market demand insufficient, experiments concluded');
        $this->assertSame('KILLED', $killed->funnel_stage);
        $this->assertFalse((bool) $killed->shared_platform_access_active);
    }

    /**
     * (f) Audit status healthy with 0 discrepancies.
     */
    public function test_ppm_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
