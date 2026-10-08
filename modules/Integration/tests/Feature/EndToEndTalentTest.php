<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EndToEndTalentService;
use Tests\TestCase;

class EndToEndTalentTest extends TestCase
{
    use RefreshDatabase;

    protected EndToEndTalentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EndToEndTalentService::class);
    }

    public function test_employee_journey_lifecycle_completeness_and_next_actions(): void
    {
        // 1. Initial stage with visible next action (254.1 & 254.7)
        $journey = $this->service->createEmployeeJourney(
            employeeId: 'EMP-7701',
            employeeName: 'Budi Santoso',
            currentStage: 'ONBOARDING',
            nextActionRequired: 'COMPLETE_SECURITY_ORIENTATION'
        );

        $this->assertEquals('ONBOARDING', $journey->current_stage);
        $this->assertEquals('COMPLETE_SECURITY_ORIENTATION', $journey->next_action_required);
        $this->assertTrue((bool) $journey->is_active);

        // 2. Advance to DEPLOYED (254.1)
        $advanced = $this->service->advanceEmployeeJourney(
            employeeId: 'EMP-7701',
            nextStage: 'DEPLOYED',
            nextActionRequired: 'COMMENCE_SMELTER_EXPANSION_PROJECT'
        );

        $this->assertEquals('DEPLOYED', $advanced->current_stage);
        $this->assertEquals('COMMENCE_SMELTER_EXPANSION_PROJECT', $advanced->next_action_required);
        $this->assertTrue((bool) $advanced->is_active);

        // 3. Exit marks inactive
        $exited = $this->service->advanceEmployeeJourney('EMP-7701', 'EXITED', 'ARCHIVE_RECORDS');
        $this->assertFalse((bool) $exited->is_active);
    }

    public function test_job_requisition_internal_first_marketplace_guard(): void
    {
        // 1. Create requisition with 14 days internal priority (254.2 & 254.5)
        $req = $this->service->createJobRequisition(
            targetRole: 'Senior Data Architect',
            skillRequirements: ['PHP', 'Python', 'Distributed Systems'],
            internalOfferDays: 14
        );

        $this->assertTrue((bool) $req->internal_marketplace_offered);
        $this->assertFalse((bool) $req->external_candidate_considered);

        // 2. Consider external candidate with documented fair process (254.5 Edge Case)
        $considered = $this->service->considerExternalCandidate(
            reqCode: $req->req_code,
            justification: 'Internal marketplace offered for 14 days; external niche specialist needed for specialized ML model.'
        );

        $this->assertTrue((bool) $considered->external_candidate_considered);
        $this->assertTrue((bool) $considered->fair_process_documented);

        // 3. Reject external candidate consideration if offered less than 7 days internally
        $prematureReq = $this->service->createJobRequisition('Cloud Admin', ['AWS'], 3);

        $this->expectException(InvalidArgumentException::class);
        $this->service->considerExternalCandidate($prematureReq->req_code, 'Premature external search attempt');
    }

    public function test_automation_scenario_triggers_reskilling_learning_plan(): void
    {
        // 1. High automation impact scenario (35% >= 20%) triggers reskilling (254.3 & 254.6)
        $highImpact = $this->service->simulateAutomationScenario(
            department: 'CUSTOMER_SUPPORT',
            automationImpactPct: 35.0,
            projectedHeadcountDelta: -20,
            costTrajectoryUsd: 450000.0
        );

        $this->assertTrue((bool) $highImpact->reskilling_plan_triggered);
        $this->assertEquals('CUSTOMER_SUPPORT', $highImpact->department);

        // 2. Low automation impact scenario (5% < 20%)
        $lowImpact = $this->service->simulateAutomationScenario(
            department: 'EXECUTIVE_LEADERSHIP',
            automationImpactPct: 5.0,
            projectedHeadcountDelta: 0,
            costTrajectoryUsd: 1200000.0
        );

        $this->assertFalse((bool) $lowImpact->reskilling_plan_triggered);
    }

    public function test_talent_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->createEmployeeJourney('EMP-AUD-1', 'Audited Emp', 'PERFORMING', 'ANNUAL_REVIEW');
        $this->service->createJobRequisition('Role', ['Skill'], 14);
        $this->service->simulateAutomationScenario('LEGAL', 25.0, -2, 200000.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: active employee without next action
        DB::table('talent_unified_employee_journeys')->insert([
            'employee_id' => 'EMP-ORPHANED',
            'employee_name' => 'Forgotten Person',
            'current_stage' => 'DEVELOPING',
            'next_action_required' => '', // Discrepancy!
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
