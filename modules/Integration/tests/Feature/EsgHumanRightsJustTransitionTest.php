<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EsgHumanRightsJustTransitionService;
use Tests\TestCase;

class EsgHumanRightsJustTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected EsgHumanRightsJustTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EsgHumanRightsJustTransitionService::class);
    }

    public function test_community_grievance_routing_and_independent_escalation(): void
    {
        // 1. Regular grievance routes to local ombudsman (288.2)
        $g1 = $this->service->submitCommunityGrievance(
            grievanceCode: 'GRV-DUST-01',
            communityGroup: 'Desa Buli',
            category: 'AIR_QUALITY',
            againstLocalManagement: false
        );
        $this->assertEquals('LOCAL_OMBUDSMAN', $g1->assigned_escalation_channel);

        // 2. Grievance against local site management automatically routes to independent headquarters (288.6 Edge Case)
        $g2 = $this->service->submitCommunityGrievance(
            grievanceCode: 'GRV-DISPUTE-LEADERSHIP',
            communityGroup: 'Masyarakat Adat Halmahera',
            category: 'LOCAL_MANAGEMENT',
            againstLocalManagement: true
        );
        $this->assertEquals('INDEPENDENT_HEADQUARTERS', $g2->assigned_escalation_channel);
    }

    public function test_remediation_closure_requires_affected_party_verification(): void
    {
        $this->service->submitCommunityGrievance('GRV-WATER-02', 'Desa Maba', 'WATER_POLLUTION');

        // 1. Attempting to close without affected-party verification rejected (288.5)
        try {
            $this->service->closeRemediationWithPartyVerification(
                grievanceCode: 'GRV-WATER-02',
                remedyDescription: 'Clean water filter installed',
                affectedPartyVerified: false // Not verified by community!
            );
            $this->fail('Expected exception for unverified remediation closure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Affected community party must formally verify remediation', $e->getMessage());
        }

        // 2. Verified closure succeeds (288.5)
        $closed = $this->service->closeRemediationWithPartyVerification(
            grievanceCode: 'GRV-WATER-02',
            remedyDescription: 'Deep bore well water filtration commissioned',
            affectedPartyVerified: true
        );
        $this->assertEquals('CLOSED_VERIFIED', $closed->status);
        $this->assertTrue((bool) $closed->affected_party_verified_closure);
    }

    public function test_just_transition_plan_enforces_income_protection(): void
    {
        // 1. Valid plan with reskilling & income protection succeeds (288.3 & 288.7)
        $plan = $this->service->createJustTransitionPlan(
            planCode: 'PLAN-COAL-TO-SOLAR-01',
            siteCode: 'PLTU-SURABAYA',
            affectedWorkers: 250,
            reskillingBudgetUsd: 1500000.0,
            incomeProtectionBudgetUsd: 2000000.0
        );
        $this->assertEquals(250, (int) $plan->affected_workforce_count);
        $this->assertEquals(2000000.0, (float) $plan->income_protection_budget_usd);

        // 2. Plan omitting income protection budget is rejected (288.7)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must include verified allocations for both reskilling and income continuity');
        $this->service->createJustTransitionPlan('PLAN-DEFECT', 'SITE-A', 50, 500000.0, 0.0);
    }

    public function test_community_benefit_fund_allocation_and_sum_distribution(): void
    {
        // 1. 2.5% formulaic allocation on $10,000,000 gross revenue = $250,000 fund (288.4)
        $fund = $this->service->allocateBenefitSharingFund('FUND-NICKEL-2026', 'PRJ-SMELTER-01', 10000000.0, 2.50);
        $this->assertEquals(250000.0, (float) $fund->allocated_fund_usd);

        // 2. Distribute funds to community-voted health clinic ($100,000) (288.4 & 288.5)
        $distributed = $this->service->distributeCommunityFund('FUND-NICKEL-2026', 100000.0);
        $this->assertEquals(100000.0, (float) $distributed->distributed_fund_usd);

        // 3. Over-distribution exceeding allocated budget is strictly blocked (288.5)
        try {
            $this->service->distributeCommunityFund('FUND-NICKEL-2026', 200000.0); // 100k + 200k = 300k > 250k
            $this->fail('Expected exception for over-distributed benefit fund');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds allocated fund balance', $e->getMessage());
        }
    }

    public function test_esg_human_rights_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->submitCommunityGrievance('GRV-AUD', 'Desa', 'AIR');
        $this->service->closeRemediationWithPartyVerification('GRV-AUD', 'Done', true);
        $this->service->createJustTransitionPlan('TR-AUD', 'SITE', 10, 100.0, 100.0);
        $this->service->allocateBenefitSharingFund('FUND-AUD', 'PRJ', 1000.0, 2.5);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: closed grievance without affected party verification
        DB::table('esg_community_grievances')->insert([
            'grievance_code' => 'GRV-BREACH-UNVERIFIED',
            'community_group_name' => 'Desa Protes',
            'issue_category' => 'LAND',
            'against_local_management' => false,
            'assigned_escalation_channel' => 'LOCAL_OMBUDSMAN',
            'remedy_description' => 'Unverified fix',
            'is_remediation_completed' => true,
            'affected_party_verified_closure' => false, // Discrepancy!
            'status' => 'CLOSED_VERIFIED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
