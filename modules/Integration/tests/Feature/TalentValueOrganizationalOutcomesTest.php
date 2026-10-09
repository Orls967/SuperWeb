<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\TalentValueOrganizationalOutcomesService;
use Tests\TestCase;

class TalentValueOrganizationalOutcomesTest extends TestCase
{
    use RefreshDatabase;

    protected TalentValueOrganizationalOutcomesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TalentValueOrganizationalOutcomesService::class);
    }

    public function test_cohort_analytics_and_k_anonymity_privacy_suppression(): void
    {
        // 1. Large cohort (k = 50 >= 10) is not suppressed (325.1 & 325.4)
        $largeCohort = $this->service->registerAnalyticsCohort(
            cohortCode: 'COHORT-ENG-SENIOR-2026',
            departmentName: 'ENGINEERING',
            sampleSizeK: 50,
            correlationValue: 0.685
        );
        $this->assertFalse((bool) $largeCohort->is_suppressed_for_privacy);
        $this->assertEquals(0.685, (float) $largeCohort->correlation_learning_to_retention);

        // 2. Small cohort (k = 5 < 10) is strictly suppressed to preserve employee anonymity (325.4 & 325.6 Risk)
        $smallCohort = $this->service->registerAnalyticsCohort(
            cohortCode: 'COHORT-SPECIAL-METALLURGY',
            departmentName: 'SMELTER_RND',
            sampleSizeK: 5,
            correlationValue: 0.910
        );
        $this->assertTrue((bool) $smallCohort->is_suppressed_for_privacy);
    }

    public function test_investment_decision_and_empirical_evidence_requirement(): void
    {
        // 1. Claiming projected benefits without empirical evidence is rejected (325.5 Edge Case)
        try {
            $this->service->registerInvestmentDecision(
                decisionCode: 'INV-UNSUBSTANTIATED-AI',
                type: 'AUTOMATION',
                costUsd: 250000.0,
                projectedBenefitUsd: 1000000.0,
                uncertaintyPct: 20.0,
                hasEmpiricalEvidence: false
            );
            $this->fail('Expected exception for unevidenced benefit claim');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Methodology review required: Cannot claim financial benefit without measured empirical evidence', $e->getMessage());
        }

        // 2. Investment decision with measured empirical evidence succeeds (325.3 & 325.5)
        $approvedDecision = $this->service->registerInvestmentDecision(
            decisionCode: 'INV-EVIDENCED-TRAINING',
            type: 'TRAINING',
            costUsd: 80000.0,
            projectedBenefitUsd: 240000.0,
            uncertaintyPct: 10.0,
            hasEmpiricalEvidence: true
        );
        $this->assertTrue((bool) $approvedDecision->claim_approved);
        $this->assertEquals(80000.0, (float) $approvedDecision->cost_usd);
    }

    public function test_hcm_outcomes_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerAnalyticsCohort('C-AUD', 'DEPT', 20, 0.5);
        $this->service->registerInvestmentDecision('INV-AUD', 'TRAINING', 1000.0, 2000.0, 10.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: small cohort unsuppressed in DB
        DB::table('human_capital_analytics_cohorts')->insert([
            'cohort_code' => 'C-LEAK-DEFECT',
            'department_name' => 'EXEC',
            'sample_size_k' => 3, // k < 10
            'correlation_learning_to_retention' => 0.8,
            'is_suppressed_for_privacy' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
