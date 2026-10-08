<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\TenderBidManagementService;
use Tests\TestCase;

class TenderBidManagementTest extends TestCase
{
    use RefreshDatabase;

    protected TenderBidManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenderBidManagementService::class);
    }

    public function test_tender_bid_qualification_deterministic_and_coi_screening(): void
    {
        // 1. High-scoring tender without COI -> BID (248.1 & 248.5)
        // Score = (85 * 0.4) + (80 * 0.4) - (20 * 0.2) = 34 + 32 - 4 = 62... wait:
        // (90 * 0.4) + (85 * 0.4) - (10 * 0.2) = 36 + 34 - 2 = 68 >= 65
        $bidOk = $this->service->evaluateBidQualification(
            tenderCode: 'TND-CLEAN-01',
            clientAgency: 'Kementerian ESDM',
            businessLine: 'ENERGY',
            contractEstimateUsd: 15000000.0,
            strategicFitScore: 90.0,
            marginScore: 85.0,
            riskScore: 10.0,
            hasRelatedPartyCoi: false
        );

        $this->assertEquals('BID', $bidOk->qualification_verdict);
        $this->assertTrue((bool) $bidOk->coi_cleared);

        // 2. High-scoring tender WITH COI -> NO_BID forced (248.7)
        $bidCoi = $this->service->evaluateBidQualification(
            tenderCode: 'TND-COI-02',
            clientAgency: 'Affiliated State Entity',
            businessLine: 'ENERGY',
            contractEstimateUsd: 25000000.0,
            strategicFitScore: 95.0,
            marginScore: 90.0,
            riskScore: 5.0,
            hasRelatedPartyCoi: true // COI present!
        );

        $this->assertEquals('NO_BID', $bidCoi->qualification_verdict);
        $this->assertFalse((bool) $bidCoi->coi_cleared);
    }

    public function test_human_approval_mandatory_before_submission(): void
    {
        $bid = $this->service->evaluateBidQualification(
            tenderCode: 'TND-PLN-01',
            clientAgency: 'PT PLN Persero',
            businessLine: 'ENERGY',
            contractEstimateUsd: 8000000.0,
            strategicFitScore: 88.0,
            marginScore: 82.0,
            riskScore: 15.0
        );

        // Approve and submit (248.3 & 248.5)
        $submitted = $this->service->approveAndSubmitBid((int) $bid->id, 'VP_BID_GOVERNANCE');
        $this->assertEquals('APPROVED', $submitted->human_approval_status);
        $this->assertEquals('SUBMITTED', $submitted->status);
    }

    public function test_bid_cost_accounting_and_cancelled_tender_preservation(): void
    {
        $bid = $this->service->evaluateBidQualification(
            tenderCode: 'TND-MRT-01',
            clientAgency: 'MRT Jakarta',
            businessLine: 'EPC',
            contractEstimateUsd: 50000000.0,
            strategicFitScore: 92.0,
            marginScore: 80.0,
            riskScore: 10.0
        );

        // Log preparation costs (248.2)
        $this->service->logBidCost((int) $bid->id, 'ENGINEERING', 75000.0, 'CAPITALIZE');
        $this->service->logBidCost((int) $bid->id, 'LEGAL', 25000.0, 'EXPENSE');

        $this->service->approveAndSubmitBid((int) $bid->id, 'VP_BID');

        // Tender cancelled by client post-submission (248.6 Edge Case)
        $cancelled = $this->service->cancelTenderByClient((int) $bid->id, 'Client revised project scope indefinitely');
        $this->assertEquals('CANCELLED_BY_CLIENT', $cancelled->status);

        // Costs and ROI tracking remain intact
        $roi = $this->service->calculateBidRoi((int) $bid->id);
        $this->assertEquals(100000.0, (float) $roi['total_bid_cost_usd']);
        $this->assertTrue($roi['is_cost_accounted']);
        $this->assertEquals(0.20, (float) $roi['bid_cost_ratio_pct']); // 100k / 50M * 100
    }

    public function test_post_award_mobilization_checklist_and_health_index(): void
    {
        // 1. Healthy mobilization (checklist = 88% >= 80%) (248.4 & 248.5)
        $healthyMob = $this->service->mobilizePostAwardProject(
            tenderId: 101,
            projectCode: 'PRJ-AWARD-ALPHA',
            first90DaysChecklistPct: 88.0
        );
        $this->assertEquals('HEALTHY', $healthyMob->project_health_index);
        $this->assertTrue((bool) $healthyMob->mobilization_cleared);

        // 2. At-risk mobilization (checklist = 35% < 50%)
        $riskMob = $this->service->mobilizePostAwardProject(
            tenderId: 102,
            projectCode: 'PRJ-AWARD-BETA',
            first90DaysChecklistPct: 35.0
        );
        $this->assertEquals('AT_RISK', $riskMob->project_health_index);
        $this->assertFalse((bool) $riskMob->mobilization_cleared);
    }

    public function test_tender_bid_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $bid = $this->service->evaluateBidQualification('TND-AUD-01', 'Agency', 'TELCO', 1000000.0, 90.0, 85.0, 5.0);
        $this->service->logBidCost((int) $bid->id, 'RESEARCH', 5000.0);
        $this->service->approveAndSubmitBid((int) $bid->id, 'VP');
        $this->service->mobilizePostAwardProject((int) $bid->id, 'PRJ-AUD', 90.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unapproved bid marked submitted
        DB::table('tender_bids')->insert([
            'tender_code' => 'TND-UNAPPROVED',
            'client_agency' => 'Ministry',
            'business_line' => 'EPC',
            'contract_estimate_usd' => 500000.0,
            'qualification_score' => 70.0,
            'qualification_verdict' => 'BID',
            'coi_screened' => true,
            'coi_cleared' => true,
            'human_approval_status' => 'PENDING', // Not approved!
            'status' => 'SUBMITTED', // But marked submitted!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
