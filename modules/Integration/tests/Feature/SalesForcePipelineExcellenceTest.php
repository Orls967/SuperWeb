<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SalesForcePipelineExcellenceService;
use Tests\TestCase;

class SalesForcePipelineExcellenceTest extends TestCase
{
    use RefreshDatabase;

    protected SalesForcePipelineExcellenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SalesForcePipelineExcellenceService::class);
    }

    public function test_territory_quota_allocation_and_overlap_guard(): void
    {
        // 1. Allocate initial territory
        $terr1 = $this->service->allocateQuotaAndTerritory(
            territoryCode: 'TERR-JKT-CENTRAL',
            region: 'GREATER_JAKARTA',
            assignedRepId: 'REP-SARAH',
            quotaTargetUsd: 1500000.0,
            fiscalYear: 2026
        );

        $this->assertEquals('TERR-JKT-CENTRAL', $terr1->territory_code);
        $this->assertEquals(1500000.0, (float) $terr1->quota_target_usd);

        // 2. Overlap attempt for same region & year is rejected (246.5)
        $this->expectException(InvalidArgumentException::class);
        $this->service->allocateQuotaAndTerritory(
            territoryCode: 'TERR-JKT-EAST',
            region: 'GREATER_JAKARTA',
            assignedRepId: 'REP-BOB',
            quotaTargetUsd: 1000000.0,
            fiscalYear: 2026
        );
    }

    public function test_b2b_deal_creation_with_independent_forecast_model(): void
    {
        $deal = $this->service->createB2bDeal(
            businessLine: 'EPC_PROJECT',
            clientName: 'PT Nusantara Petrochemical',
            dealValueUsd: 10000000.0,
            territoryId: 1,
            repConfidencePct: 99.0, // Rep claims 99%
            stage: 'QUALIFICATION'
        );

        // Independent model overrides rep hype to objective baseline (15% for Qualification) (246.6 Edge Case)
        $this->assertEquals(15.0, (float) $deal->independent_model_win_prob_pct);
        $this->assertEquals(1500000.0, (float) $deal->weighted_forecast_usd);
    }

    public function test_deal_stage_advancement_exit_criteria_gate(): void
    {
        $deal = $this->service->createB2bDeal(
            businessLine: 'INSURANCE',
            clientName: 'Global Maritime Logistics',
            dealValueUsd: 2000000.0,
            territoryId: 1,
            repConfidencePct: 50.0,
            stage: 'QUALIFICATION'
        );

        // 1. Rejection when exit criteria not met (246.1)
        try {
            $this->service->advanceDealStage((int) $deal->id, 'SOLUTION_DESIGN', false);
            $this->fail('Expected exception for unmet exit criteria');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Exit criteria not satisfied', $e->getMessage());
        }

        // 2. Success when exit criteria met -> updates objective probability to 35%
        $advanced = $this->service->advanceDealStage((int) $deal->id, 'SOLUTION_DESIGN', true);
        $this->assertEquals('SOLUTION_DESIGN', $advanced->deal_stage);
        $this->assertEquals(35.0, (float) $advanced->independent_model_win_prob_pct);
        $this->assertEquals(700000.0, (float) $advanced->weighted_forecast_usd);
    }

    public function test_win_loss_analysis_structured_capture(): void
    {
        $deal = $this->service->createB2bDeal('TELECOM', 'Telco Group', 500000.0, 1, 80.0, 'NEGOTIATION');
        $this->service->advanceDealStage((int) $deal->id, 'CLOSED_WON', true);

        $analysis = $this->service->recordWinLossAnalysis(
            dealId: (int) $deal->id,
            outcome: 'WON',
            primaryFactor: 'TECHNICAL_FIT',
            competitorName: 'Legacy Telco Inc',
            discountPctApproved: 5.50
        );

        $this->assertEquals('WON', $analysis->outcome);
        $this->assertEquals('TECHNICAL_FIT', $analysis->primary_decision_factor);
        $this->assertEquals(5.50, (float) $analysis->discount_pct_approved);
    }

    public function test_territory_dispute_documented_arbitration(): void
    {
        $dispute = $this->service->arbitrateTerritoryDispute(
            dealId: 42,
            claimingRepA: 'REP-SARAH',
            claimingRepB: 'REP-ANDREW',
            disputeRuleApplied: 'ACCOUNT_HQ_ORIGIN',
            decisionNotes: 'Client headquarters is in Jakarta; awarded to Rep Sarah per governance playbook rule 4.2.',
            arbitratedBy: 'VP_SALES_NATIONAL'
        );

        $this->assertEquals('RESOLVED', $dispute->status);
        $this->assertEquals('ACCOUNT_HQ_ORIGIN', $dispute->dispute_rule_applied);
        $this->assertEquals('VP_SALES_NATIONAL', $dispute->arbitrated_by);
        $this->assertStringStartsWith('DSP-', $dispute->dispute_code);
    }

    public function test_sales_force_audit_healthy_and_discrepancy(): void
    {
        // Healthy setup
        $deal = $this->service->createB2bDeal('COLOCATION', 'Data Center Client', 100000.0, 1, 20.0, 'QUALIFICATION');
        $this->service->advanceDealStage((int) $deal->id, 'CLOSED_WON', true);
        $this->service->recordWinLossAnalysis((int) $deal->id, 'WON', 'SLA_TERMS');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: closed deal missing win/loss analysis
        $unreviewedClosed = $this->service->createB2bDeal('SERVICES', 'Mystery Client', 50000.0, 1, 10.0, 'QUALIFICATION');
        DB::table('sales_pipeline_deals')
            ->where('id', $unreviewedClosed->id)
            ->update([
                'deal_stage' => 'CLOSED_LOST',
                'stage_exit_criteria_met' => true,
            ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
