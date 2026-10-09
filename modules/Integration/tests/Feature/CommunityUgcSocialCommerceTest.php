<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\CommunityUgcSocialCommerceService;
use Tests\TestCase;

class CommunityUgcSocialCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected CommunityUgcSocialCommerceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CommunityUgcSocialCommerceService::class);
    }

    public function test_ugc_review_verification_and_creator_points_incentive(): void
    {
        // 1. Unverified review awards 0 creator points (282.2 & 282.4)
        $unverified = $this->service->submitReview('USER_ANON', 'Looks nice', null);
        $this->assertFalse((bool) $unverified->is_purchase_verified);
        $this->assertEquals(0, (int) $unverified->creator_points_awarded);

        // 2. Verified purchase review awards 50 points and high trust score (282.2 & 282.7)
        $verified = $this->service->submitReview('USER_BUYER_01', 'High quality nickel battery cell', 'ORD-2026-NICKEL-99');
        $this->assertTrue((bool) $verified->is_purchase_verified);
        $this->assertEquals(50, (int) $verified->creator_points_awarded);
        $this->assertEquals(8.50, (float) $verified->contributor_trust_score);
    }

    public function test_ugc_pii_violation_triggers_instant_moderation_removal(): void
    {
        // Review containing leaked customer PII (phone/address) (282.5 Edge Case)
        $leakedReview = $this->service->submitReview(
            authorUserId: 'USER_MALICIOUS',
            content: 'Contact the courier directly at 08123456789 or visit his home at Jl. Sudirman No 10',
            verifiedOrderId: 'ORD-LEAK',
            containsPii: true
        );

        $this->assertEquals('REMOVED_PII_VIOLATION', $leakedReview->moderation_status);
        $this->assertTrue((bool) $leakedReview->contains_pii_violation);
        $this->assertEquals(0, (int) $leakedReview->creator_points_awarded); // 0 points awarded
        $this->assertEquals(1.00, (float) $leakedReview->contributor_trust_score); // Severely downgraded
    }

    public function test_live_social_commerce_idempotency_and_stockout_auto_refund(): void
    {
        $this->service->setLiveInventory('SKU-LIPSTICK-RED', 10);

        // 1. Normal live order placed (282.3 & 282.4)
        $order1 = $this->service->placeLiveOrder(
            idempotencyKey: 'IDEMP-LIVE-TX-001',
            liveSessionId: 'LIVE-TIKTOK-SIM-01',
            productSku: 'SKU-LIPSTICK-RED',
            quantity: 3,
            unitPriceUsd: 20.0,
            hostCommissionRatePct: 10.0
        );
        $this->assertEquals('ORDER_PLACED', $order1->status);
        $this->assertEquals(6.0, (float) $order1->host_commission_usd);
        $this->assertFalse((bool) $order1->refund_issued);

        // 2. Idempotent re-submission returns identical order without double deducting stock (282.4)
        $duplicate = $this->service->placeLiveOrder(
            idempotencyKey: 'IDEMP-LIVE-TX-001',
            liveSessionId: 'LIVE-TIKTOK-SIM-01',
            productSku: 'SKU-LIPSTICK-RED',
            quantity: 3,
            unitPriceUsd: 20.0
        );
        $this->assertEquals($order1->idempotency_key, $duplicate->idempotency_key);

        // 3. Stock exhaustion mid-stream -> auto-cancel & instant refund (282.6 Edge Case)
        // 7 left; requesting 15 items exceeds stock
        $cancelled = $this->service->placeLiveOrder(
            idempotencyKey: 'IDEMP-LIVE-TX-OVERFLOW',
            liveSessionId: 'LIVE-TIKTOK-SIM-01',
            productSku: 'SKU-LIPSTICK-RED',
            quantity: 15,
            unitPriceUsd: 20.0
        );
        $this->assertEquals('AUTO_CANCELLED_OUT_OF_STOCK', $cancelled->status);
        $this->assertTrue((bool) $cancelled->refund_issued);
    }

    public function test_community_ugc_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->submitReview('U1', 'Good', 'ORD-1');
        $this->service->setLiveInventory('SKU-1', 100);
        $this->service->placeLiveOrder('K1', 'L1', 'SKU-1', 1, 10.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unverified review receiving creator points
        DB::table('ugc_community_reviews')->insert([
            'review_code' => 'REV-UNVERIFIED-EXPLOIT',
            'author_user_id' => 'USER_EXPLOIT',
            'verified_order_id' => null,
            'review_content' => 'Exploit points',
            'is_purchase_verified' => false,
            'contains_pii_violation' => false,
            'moderation_status' => 'APPROVED',
            'creator_points_awarded' => 100, // Discrepancy!
            'contributor_trust_score' => 5.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
