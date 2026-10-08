<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DistributorRetailerCollaborationService;
use Tests\TestCase;

class DistributorRetailerCollaborationTest extends TestCase
{
    use RefreshDatabase;

    protected DistributorRetailerCollaborationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DistributorRetailerCollaborationService::class);
    }

    public function test_jbp_agreement_creation_and_net_incentive_settlement(): void
    {
        // 1. Create JBP Agreement (262.1)
        $jbp = $this->service->createJbpAgreement(
            jbpCode: 'JBP-INDOMARET-2026',
            retailerCode: 'RET-INDOMARET-CORP',
            targetVolumeUnits: 100000,
            baseIncentiveRateUsd: 25000.0
        );

        $this->assertEquals('JBP-INDOMARET-2026', $jbp->jbp_code);
        $this->assertEquals(25000.0, (float) $jbp->base_incentive_rate_usd);

        // 2. Settle JBP with 100% volume achievement, 92% data quality, $1000 planogram penalty (262.1, 262.4, 262.7)
        // Base = 25000; Data quality bonus = 92% of 5000 = 4600; Penalty = 1000; Net = 28600
        $settled = $this->service->settleJbpIncentive(
            jbpCode: 'JBP-INDOMARET-2026',
            actualUnitsSold: 100000,
            feedDataQualityScore: 92.0,
            planogramPenaltyUsd: 1000.0
        );

        $this->assertEquals(4600.0, (float) $settled->data_quality_incentive_usd);
        $this->assertEquals(1000.0, (float) $settled->planogram_penalty_usd);
        $this->assertEquals(28600.0, (float) $settled->net_settlement_incentive_usd);
    }

    public function test_pos_sellout_feed_idempotency(): void
    {
        // 1. First ingestion (262.2 & 262.4)
        $feed1 = $this->service->ingestPosSelloutFeed(
            idempotencyKey: 'FEED-KEY-POS-9988',
            retailerCode: 'RET-ALFAMART',
            outletCode: 'OUTLET-JKT-101',
            batchDate: '2026-10-08',
            unitsSold: 350,
            revenueUsd: 1750.0
        );

        // 2. Duplicate ingestion with identical key returns existing instance without creating new row
        $feed2 = $this->service->ingestPosSelloutFeed(
            idempotencyKey: 'FEED-KEY-POS-9988',
            retailerCode: 'RET-ALFAMART',
            outletCode: 'OUTLET-JKT-101',
            batchDate: '2026-10-08',
            unitsSold: 350,
            revenueUsd: 1750.0
        );

        $this->assertEquals($feed1->id, $feed2->id);
        $this->assertEquals(1, DB::table('distributor_pos_sellout_feeds')->count());
    }

    public function test_delayed_sellout_feed_fallback_proxy_and_confidence_score(): void
    {
        // Delayed feed falls back to regional proxy with confidence score 0.650 (262.5 Edge Case)
        $fallback = $this->service->ingestPosSelloutFeed(
            idempotencyKey: 'FEED-DELAYED-KEY-77',
            retailerCode: 'RET-SUPERINDO',
            outletCode: 'OUTLET-SBY-02',
            batchDate: '2026-10-08',
            unitsSold: 500,
            revenueUsd: 2500.0,
            isFeedDelayedOrCorrupted: true
        );

        $this->assertEquals('DELAYED_FALLBACK_USED', $fallback->feed_status);
        $this->assertEquals(0.650, (float) $fallback->forecast_confidence_score);
    }

    public function test_planogram_compliance_dispute_requires_photo_evidence(): void
    {
        // 1. Dispute without photo evidence is rejected (262.6)
        try {
            $this->service->recordPlanogramAudit(
                outletCode: 'OUTLET-DISPUTE-01',
                compliancePct: 65.0,
                isDisputed: true,
                photoEvidenceDoc: null // Missing!
            );
            $this->fail('Expected exception for missing photo evidence');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('mandatory photographic evidence', $e->getMessage());
        }

        // 2. Dispute with photo evidence succeeds with documented resolution (262.3 & 262.6)
        $audit = $this->service->recordPlanogramAudit(
            outletCode: 'OUTLET-DISPUTE-01',
            compliancePct: 65.0,
            isDisputed: true,
            photoEvidenceDoc: 'DOC-SHELF-PHOTO-PROOF-2026.JPG'
        );

        $this->assertTrue((bool) $audit->is_disputed);
        $this->assertEquals('DISPUTE_UPHELD_PARTIAL_WAIVER', $audit->dispute_resolution_outcome);
        $this->assertEquals('DOC-SHELF-PHOTO-PROOF-2026.JPG', $audit->dispute_photo_evidence_doc);
    }

    public function test_distributor_collaboration_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $jbp = $this->service->createJbpAgreement('JBP-AUD', 'RET-AUD', 100, 1000.0);
        $this->service->settleJbpIncentive('JBP-AUD', 100, 100.0, 0.0);
        $this->service->ingestPosSelloutFeed('KEY-AUD', 'RET-AUD', 'OUT-1', '2026-10-08', 10, 50.0);
        $this->service->recordPlanogramAudit('OUT-1', 95.0, false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: disputed audit without photo evidence
        DB::table('distributor_planogram_audits')->insert([
            'audit_code' => 'PLANO-DISP-BAD',
            'outlet_code' => 'OUT-BAD',
            'compliance_pct' => 50.0,
            'is_disputed' => true,
            'dispute_photo_evidence_doc' => null, // Discrepancy!
            'dispute_resolution_outcome' => 'NONE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
