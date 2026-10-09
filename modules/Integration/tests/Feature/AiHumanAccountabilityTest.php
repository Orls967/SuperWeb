<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AiHumanAccountabilityService;
use Tests\TestCase;

class AiHumanAccountabilityTest extends TestCase
{
    use RefreshDatabase;

    protected AiHumanAccountabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiHumanAccountabilityService::class);
    }

    public function test_transparency_disclosure_requirement(): void
    {
        // 1. Material decision without disclosure throws exception (350.3 & 350.4)
        try {
            $this->service->issueMaterialOutcomeDecision(
                decisionCode: 'DEC-PRICING-DYNAMIC-01',
                userId: 'USER-CUSTOMER-991',
                category: 'PRICING',
                disclosureProvided: false // No disclosure!
            );
            $this->fail('Expected exception for missing transparency disclosure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Material outcome decision requires user transparency disclosure', $e->getMessage());
        }

        // 2. Decision with disclosure succeeds (350.3 & 350.4)
        $decision = $this->service->issueMaterialOutcomeDecision(
            decisionCode: 'DEC-PRICING-DYNAMIC-02',
            userId: 'USER-CUSTOMER-991',
            category: 'PRICING',
            disclosureProvided: true
        );
        $this->assertTrue((bool) $decision->transparency_disclosure_provided);
        $this->assertTrue((bool) $decision->has_appeal_path);
    }

    public function test_appeal_human_reviewer_and_sla_edge_case(): void
    {
        // Issue decision first
        $this->service->issueMaterialOutcomeDecision('DEC-CREDIT-01', 'USER-10', 'CREDIT', true);

        // 1. Appeal without assigned human reviewer throws exception (350.5 Edge Case)
        try {
            $this->service->resolveAppeal(
                appealCode: 'APP-FAIL-NO-HUMAN',
                decisionCode: 'DEC-CREDIT-01',
                humanReviewerId: null, // No human reviewer!
                turnaroundHours: 12.0
            );
            $this->fail('Expected exception for missing human reviewer on appeal');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Appeals against AI decisions require an assigned human reviewer', $e->getMessage());
        }

        // 2. Appeal with human reviewer within SLA succeeds (350.4)
        $appeal = $this->service->resolveAppeal(
            appealCode: 'APP-RESOLVED-CLEAN',
            decisionCode: 'DEC-CREDIT-01',
            humanReviewerId: 'REVIEWER-ETHICS-OFFICER-01',
            turnaroundHours: 24.0,
            slaHours: 48.0
        );
        $this->assertEquals('RESOLVED_HUMAN', $appeal->status);
        $this->assertFalse((bool) $appeal->sla_breached);

        // 3. Turnaround exceeding SLA flags breach (350.5)
        $lateAppeal = $this->service->resolveAppeal(
            appealCode: 'APP-RESOLVED-LATE',
            decisionCode: 'DEC-CREDIT-01',
            humanReviewerId: 'REVIEWER-ETHICS-OFFICER-01',
            turnaroundHours: 55.0, // 55 > 48 SLA!
            slaHours: 48.0
        );
        $this->assertTrue((bool) $lateAppeal->sla_breached);
    }

    public function test_ethics_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->issueMaterialOutcomeDecision('D-AUD', 'U1', 'CREDIT', true);
        $this->service->resolveAppeal('A-AUD', 'D-AUD', 'REV-1', 10.0, 48.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: resolved without human reviewer
        DB::table('ai_decision_appeals')->insert([
            'appeal_code' => 'APP-DEFECT-NO-HUMAN',
            'decision_code' => 'D-AUD',
            'assigned_human_reviewer_id' => null, // Discrepancy!
            'response_turnaround_hours' => 10.0,
            'sla_hours' => 48.0,
            'sla_breached' => false,
            'status' => 'RESOLVED_HUMAN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
