<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\UnifiedControlTowerDecisionService;
use Tests\TestCase;

class UnifiedControlTowerDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected UnifiedControlTowerDecisionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UnifiedControlTowerDecisionService::class);
    }

    public function test_recommendation_execution_rejection_without_approval_risk(): void
    {
        // 1. Create recommendation requiring approval (388.2 & 388.4)
        $this->service->createRecommendation(
            decisionCode: 'DEC-CAPEX-REALLOCATION-01',
            recommendationTitle: 'Reallocate 20M capex to battery energy storage',
            requiresDelegatedApproval: true
        );

        // 2. Execution without approval is strictly rejected (388.4 & 388.6 Risk)
        try {
            $this->service->executeDecision(
                decisionCode: 'DEC-CAPEX-REALLOCATION-01',
                approvalGranted: false // Unauthorized!
            );
            $this->fail('Expected exception for executing decision without delegated approval');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires delegated approval before execution', $e->getMessage());
        }

        // 3. Execution with approval succeeds (388.2 & 388.4)
        $executed = $this->service->executeDecision(
            decisionCode: 'DEC-CAPEX-REALLOCATION-01',
            approvalGranted: true
        );
        $this->assertTrue((bool) $executed->approval_granted);
        $this->assertTrue((bool) $executed->decision_executed);
    }

    public function test_stalled_approval_escalates_to_next_tier_edge_case(): void
    {
        // Recommendation starts in Tier 1
        $decision = $this->service->createRecommendation(
            decisionCode: 'DEC-SUPPLIER-SWITCH-01',
            recommendationTitle: 'Switch primary lithium provider'
        );
        $this->assertEquals('TIER_1', $decision->approval_escalation_tier);

        // Stalled approval escalates to Tier 2 (388.3 & 388.5 Edge Case)
        $escalated = $this->service->escalateStalledApproval('DEC-SUPPLIER-SWITCH-01');
        $this->assertEquals('TIER_2_EXECUTIVE_ESCALATED', $escalated->approval_escalation_tier);
    }

    public function test_control_tower_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->createRecommendation('DEC-AUD', 'AUDIT DECISION', true);
        $this->service->executeDecision('DEC-AUD', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: executed decision without approval
        DB::table('global_control_tower_decision_loops')->insert([
            'decision_code' => 'DEC-DEFECT-UNAPPROVED',
            'recommendation_title' => 'TITLE',
            'requires_delegated_approval' => true,
            'approval_granted' => false, // Discrepancy!
            'decision_executed' => true, // Discrepancy!
            'approval_escalation_tier' => 'TIER_1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
