<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\WorkforceAutomationRoleDesignService;
use Tests\TestCase;

class WorkforceAutomationRoleDesignTest extends TestCase
{
    use RefreshDatabase;

    protected WorkforceAutomationRoleDesignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkforceAutomationRoleDesignService::class);
    }

    public function test_role_automation_assessment_and_counter_metric(): void
    {
        // 1. Assessment passing quality counter-metric (99.0% >= 98.0%) (319.1 & 319.6 Risk)
        $assessment = $this->service->assessRoleAutomation(
            assessmentCode: 'ASSESS-AP-AUTOMATION-01',
            functionName: 'FINANCE_AP_AR',
            automatableTaskPct: 65.0,
            realizedQualityPct: 99.2,
            minQualityThreshold: 98.0
        );
        $this->assertEquals(65.0, (float) $assessment->automatable_task_pct);
        $this->assertFalse((bool) $assessment->labor_governance_approved);

        // 2. Assessment violating quality counter-metric (95.0% < 98.0%) is rejected (319.6)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quality counter-metric breach: Automation quality');
        $this->service->assessRoleAutomation('ASSESS-DEFECT', 'SUPPORT', 80.0, 95.0, 98.0);
    }

    public function test_just_transition_redeployment_before_layoff_gate(): void
    {
        $this->service->assessRoleAutomation('ASSESS-DISPATCH-02', 'FLEET_DISPATCH', 50.0, 99.0, 98.0);

        // 1. Just transition plan creation fails without governance approval (319.2)
        try {
            $this->service->registerJustTransitionPlan('PLAN-FAIL', 'ASSESS-DISPATCH-02', 20, 20);
            $this->fail('Expected exception for unapproved labor governance');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Just Transition plan requires approved labor governance', $e->getMessage());
        }

        // 2. Approve governance (319.2)
        $this->service->approveLaborGovernance('ASSESS-DISPATCH-02');

        // 3. Partial redeployment (10 out of 20) prohibits layoff (319.5 Edge Case)
        $partialPlan = $this->service->registerJustTransitionPlan('PLAN-PARTIAL', 'ASSESS-DISPATCH-02', 20, 10);
        $this->assertFalse((bool) $partialPlan->just_transition_redeployment_completed);
        $this->assertFalse((bool) $partialPlan->layoff_permitted);

        // 4. 100% redeployment fulfilled (20 out of 20) completes transition (319.5)
        $fullPlan = $this->service->registerJustTransitionPlan('PLAN-FULL', 'ASSESS-DISPATCH-02', 20, 20);
        $this->assertTrue((bool) $fullPlan->just_transition_redeployment_completed);
        $this->assertTrue((bool) $fullPlan->layoff_permitted);
    }

    public function test_hcm_automation_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->assessRoleAutomation('ASSESS-AUD', 'FUNCTION', 40.0, 99.0, 98.0);
        $this->service->approveLaborGovernance('ASSESS-AUD');
        $this->service->registerJustTransitionPlan('PLAN-AUD', 'ASSESS-AUD', 5, 5);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: layoff permitted while redeployment incomplete
        DB::table('workforce_just_transition_plans')->insert([
            'plan_code' => 'PLAN-UNJUST-LAYOFF',
            'assessment_code' => 'ASSESS-AUD',
            'impacted_headcount' => 50,
            'redeployed_or_reskilled_count' => 10,
            'just_transition_redeployment_completed' => false,
            'layoff_permitted' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
