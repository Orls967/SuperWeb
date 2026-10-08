<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AdvancedAnalyticsGraphResearchService;
use Tests\TestCase;

class AdvancedAnalyticsGraphResearchTest extends TestCase
{
    use RefreshDatabase;

    protected AdvancedAnalyticsGraphResearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdvancedAnalyticsGraphResearchService::class);
    }

    public function test_graph_analytics_centrality_and_suspicious_flow_detection(): void
    {
        // Hub distributor node
        $this->service->addGraphEdge('SUPPLIER_A', 'HUB_DISTRIBUTOR', 'SUPPLY_CHAIN', 500000.0);
        $this->service->addGraphEdge('SUPPLIER_B', 'HUB_DISTRIBUTOR', 'SUPPLY_CHAIN', 600000.0);
        $this->service->addGraphEdge('HUB_DISTRIBUTOR', 'RETAILER_X', 'DISTRIBUTION', 300000.0);
        // Suspicious money flow
        $this->service->addGraphEdge('OFFSHORE_SHELL', 'HUB_DISTRIBUTOR', 'PAYMENT_FLOW', 50000.0, true);

        $centrality = $this->service->calculateCentrality('HUB_DISTRIBUTOR');

        $this->assertEquals(3, $centrality['in_degree']);
        $this->assertEquals(1, $centrality['out_degree']);
        $this->assertEquals(1450000.0, $centrality['flow_volume']);
        $this->assertTrue($centrality['has_suspicious_flow']);
        $this->assertEquals('HIGH', $centrality['risk_concentration_level']);
    }

    public function test_monte_carlo_simulation_deterministic_seed_and_extreme_clamping(): void
    {
        // 1. Deterministic simulation with same seed produces identical results (243.5)
        $sim1 = $this->service->runMonteCarloSimulation('DEMAND_SHOCK', 42, 100, 100000.0);
        $sim2 = $this->service->runMonteCarloSimulation('DEMAND_SHOCK', 42, 100, 100000.0);

        $this->assertEquals($sim1->var_95_loss_estimate, $sim2->var_95_loss_estimate);
        $this->assertEquals($sim1->clamped_decision_limit, $sim2->clamped_decision_limit);

        // 2. Outlier check & decision clamping (243.6 Edge Case)
        if ($sim1->is_extreme_outlier) {
            $this->assertLessThanOrEqual($sim1->var_95_loss_estimate, $sim1->clamped_decision_limit);
            $this->assertEquals(150000.0, (float) $sim1->clamped_decision_limit);
        }
    }

    public function test_prescriptive_analytics_impact_tracking_and_production_ai_governance(): void
    {
        // 1. Create prescriptive recommendation
        $rec = $this->service->createPrescriptiveRecommendation(
            domainLine: 'LOGISTICS',
            recommendationTitle: 'Dynamic Dispatch Load Consolidation',
            expectedImpactUsd: 120000.0
        );

        $this->assertEquals(120000.0, (float) $rec->expected_impact_usd);
        $this->assertNull($rec->actual_impact_usd);
        $this->assertFalse((bool) $rec->is_production_promoted);

        // 2. Track actual measured impact (243.3)
        $updatedRec = $this->service->recordActualImpact((int) $rec->id, 114500.0);
        $this->assertEquals(114500.0, (float) $updatedRec->actual_impact_usd);

        // 3. Promote to production with AI governance review (243.7)
        $promoted = $this->service->promoteModelToProduction((int) $rec->id, 'AI_GOVERNANCE_COUNCIL');
        $this->assertTrue((bool) $promoted->is_production_promoted);
        $this->assertTrue((bool) $promoted->ai_governance_reviewed);
    }

    public function test_research_governance_irb_ethical_checklist_guard(): void
    {
        // 1. Non-sensitive experiment approved by default
        $publicExp = $this->service->registerResearchExperiment(
            title: 'Anonymized macro trend index analysis',
            usesSensitiveData: false,
            ethicalChecklistCompleted: true
        );
        $this->assertTrue((bool) $publicExp->irb_ethical_approved);

        // 2. Sensitive experiment requires IRB approval (243.4)
        $sensitiveExp = $this->service->registerResearchExperiment(
            title: 'Customer behavioral credit score experimentation',
            usesSensitiveData: true,
            ethicalChecklistCompleted: false
        );
        $this->assertFalse((bool) $sensitiveExp->irb_ethical_approved);

        // Reject approval without ethical checklist
        try {
            $this->service->approveIrbResearch((int) $sensitiveExp->id, 'CHAIR_PROF_LEE');
            $this->fail('Expected exception when ethical checklist incomplete');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Ethical checklist is not completed', $e->getMessage());
        }

        // Complete checklist and approve (243.4 & 243.5)
        DB::table('analytics_research_experiments')
            ->where('id', $sensitiveExp->id)
            ->update(['ethical_checklist_completed' => true]);

        $approved = $this->service->approveIrbResearch((int) $sensitiveExp->id, 'CHAIR_PROF_LEE');
        $this->assertTrue((bool) $approved->irb_ethical_approved);
        $this->assertEquals('CHAIR_PROF_LEE', $approved->approved_by);
    }

    public function test_analytics_research_audit_healthy_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->addGraphEdge('A', 'B', 'DISTRIBUTION', 100.0);
        $this->service->runMonteCarloSimulation('WEATHER', 99, 50, 10000.0);
        $rec = $this->service->createPrescriptiveRecommendation('FARM', 'Seed Timing', 5000.0);
        $this->service->promoteModelToProduction((int) $rec->id, 'REVIEWER');
        $this->service->registerResearchExperiment('Open trend', false, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: sensitive experiment unapproved by IRB
        DB::table('analytics_research_experiments')->insert([
            'experiment_code' => 'EXP-ROGUE',
            'title' => 'Unapproved Patient Health Correlation',
            'uses_sensitive_data' => true,
            'ethical_checklist_completed' => false,
            'irb_ethical_approved' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
