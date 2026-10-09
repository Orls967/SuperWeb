<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DecisionIntelligencePlatformService;
use Tests\TestCase;

class DecisionIntelligencePlatformTest extends TestCase
{
    use RefreshDatabase;

    protected DecisionIntelligencePlatformService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DecisionIntelligencePlatformService::class);
    }

    public function test_scenario_workbench_architectural_isolation_from_production(): void
    {
        // 1. Synthetic what-if workbench run executes deterministically (266.3 & 266.4)
        $workbench = $this->service->runScenarioWorkbench(
            scenarioCode: 'SCEN-COAL-PRICE-DROP',
            scenarioTitle: 'Impact of 20% Coal Price Drop on EBITDA',
            syntheticInputParams: ['price' => 80.0, 'volume' => 50000, 'unit_cost' => 50.0]
        );

        $this->assertTrue((bool) $workbench->is_sandbox_isolated);
        $this->assertFalse((bool) $workbench->production_data_touched);

        $outcome = json_decode($workbench->deterministic_outcome_result_json, true);
        $this->assertEquals(4000000.0, (float) $outcome['projected_revenue_usd']); // 80 * 50,000

        // 2. Architectural violation: attempting to touch real production data is blocked (266.4 & 266.7)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('strictly prohibited from touching real production data');
        $this->service->runScenarioWorkbench('SCEN-ROGUE', 'Rogue Run', [], true);
    }

    public function test_decision_catalog_and_governance_exception_guard(): void
    {
        // 1. Normal decision with workbench passed (266.1)
        $compliantDec = $this->service->catalogDecision(
            decisionCode: 'DEC-PRICING-2026-01',
            domainLine: 'PRICING',
            decisionTitle: 'Quarterly Industrial Power Tariff Adjustment',
            ownerRole: 'CHIEF_COMMERCIAL_OFFICER',
            modelUsed: 'TARIFF_OPTIMIZER_ML',
            predictedOutcomeValue: 12000000.0,
            passedWorkbenchSimulation: true
        );
        $this->assertFalse((bool) $compliantDec->is_governance_exception);
        $this->assertNull($compliantDec->governance_exception_notes);

        // 2. Decision bypassing workbench simulation logged as governance exception (266.5 Edge Case)
        $exceptionDec = $this->service->catalogDecision(
            decisionCode: 'DEC-CAPEX-UNSIMULATED',
            domainLine: 'CAPITAL',
            decisionTitle: 'Spontaneous Heavy Machinery Acquisition',
            ownerRole: 'MINING_DIR',
            modelUsed: 'HEURISTIC_GUT_FEEL',
            predictedOutcomeValue: 8000000.0,
            passedWorkbenchSimulation: false // Bypassed!
        );
        $this->assertTrue((bool) $exceptionDec->is_governance_exception);
        $this->assertNotNull($exceptionDec->governance_exception_notes);
        $this->assertStringContainsString('GOVERNANCE EXCEPTION', $exceptionDec->governance_exception_notes);
    }

    public function test_decision_retrospective_evaluation_and_bias_detection(): void
    {
        // Decision predicting $1,000,000 outcome
        $dec = $this->service->catalogDecision(
            decisionCode: 'DEC-STAFFING-PRED',
            domainLine: 'STAFFING',
            decisionTitle: 'Headcount ramp in smelter facility',
            ownerRole: 'HR_DIR',
            modelUsed: 'PRODUCTIVITY_FORECASTER',
            predictedOutcomeValue: 1000000.0
        );

        // 1. Accurate evaluation ($950,000 vs $1,000,000 = 95% accuracy): no bias (266.6)
        $accurateScore = $this->service->evaluateDecisionOutcome((int) $dec->id, 950000.0);
        $this->assertEquals(95.0, (float) $accurateScore->prediction_accuracy_pct);
        $this->assertFalse((bool) $accurateScore->bias_detected);
        $this->assertFalse((bool) $accurateScore->manager_training_recommended);

        // 2. Inaccurate evaluation with systemic bias (< 75% accuracy) recommends training (266.2)
        $biasedDec = $this->service->catalogDecision(
            decisionCode: 'DEC-BIASED-PROMO',
            domainLine: 'ALLOCATION',
            decisionTitle: 'Marketing Campaign Return on Spend',
            ownerRole: 'MARKETING_LEAD',
            modelUsed: 'LINEAR_HEURISTIC',
            predictedOutcomeValue: 2000000.0
        );
        $biasedScore = $this->service->evaluateDecisionOutcome((int) $biasedDec->id, 800000.0); // 40% accuracy!
        $this->assertTrue((bool) $biasedScore->bias_detected);
        $this->assertTrue((bool) $biasedScore->manager_training_recommended);
    }

    public function test_decision_intelligence_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->runScenarioWorkbench('SCEN-AUD', 'Audited What-If', ['price' => 10, 'volume' => 10]);
        $dec = $this->service->catalogDecision('DEC-AUD', 'RISK', 'Risk mitigation', 'CRO', 'MODEL', 100.0, true);
        $this->service->evaluateDecisionOutcome((int) $dec->id, 95.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: production data touched in sandbox
        DB::table('decision_scenario_workbenches')->insert([
            'scenario_code' => 'SCEN-LEAK-BREACH',
            'scenario_title' => 'Breached Run',
            'is_sandbox_isolated' => false,
            'production_data_touched' => true, // Discrepancy!
            'simulation_input_params_json' => json_encode([]),
            'deterministic_outcome_result_json' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
