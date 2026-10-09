<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PartnerCosellAffiliateService;
use Tests\TestCase;

class PartnerCosellAffiliateTest extends TestCase
{
    use RefreshDatabase;

    protected PartnerCosellAffiliateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PartnerCosellAffiliateService::class);
    }

    public function test_partner_tier_assignment_and_downgrade_notice_guard(): void
    {
        // 1. $350k revenue assigns GOLD tier deterministically (260.1 & 260.4)
        $partner = $this->service->registerPartner(
            partnerCode: 'PTN-CLOUD-TECH',
            partnerName: 'Cloud Tech Systems',
            ytdRevenueUsd: 350000.0
        );
        $this->assertEquals('GOLD', $partner->tier_level);

        // 2. Downgrade without 30 days notice is rejected (260.7)
        try {
            $this->service->downgradePartnerTier('PTN-CLOUD-TECH', 'SILVER', 14);
            $this->fail('Expected exception for insufficient notice');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('at least 30 days notice period', $e->getMessage());
        }

        // 3. Downgrade with 30 days notice succeeds
        $downgraded = $this->service->downgradePartnerTier('PTN-CLOUD-TECH', 'SILVER', 30);
        $this->assertEquals('SILVER', $downgraded->tier_level);
        $this->assertTrue((bool) $downgraded->downgrade_pending);
        $this->assertEquals(30, (int) $downgraded->downgrade_notice_days);
    }

    public function test_cosell_deal_registration_and_attribution_conflict_resolution(): void
    {
        // 1. Co-sell deal with attribution conflict resolved via documented priority rule (260.2 & 260.6)
        $deal = $this->service->registerCoSellDeal(
            dealCode: 'CSL-TELCO-ENTERPRISE-01',
            partnerCode: 'PTN-RESELLER-ALPHA',
            dealValueUsd: 200000.0,
            revSharePct: 15.00,
            conflictingPartnerCode: 'PTN-RESELLER-BETA',
            priorityRule: 'FIRST_TOUCH_REGISTRATION_PRIORITY'
        );

        $this->assertEquals(200000.0, (float) $deal->deal_value_usd);
        $this->assertEquals(30000.0, (float) $deal->settlement_amount_usd); // 15% of 200,000
        $this->assertTrue((bool) $deal->attribution_conflict_resolved);
        $this->assertEquals('FIRST_TOUCH_REGISTRATION_PRIORITY', $deal->attribution_priority_rule);
    }

    public function test_affiliate_referral_tracking_and_self_dealing_fraud_detection(): void
    {
        // 1. Clean referral (260.3)
        $clean = $this->service->trackAffiliateReferral(
            affiliateId: 'AFF-CREATOR-LISA',
            buyerId: 'CUST-CONSUMER-BOB',
            saleAmountUsd: 1500.0,
            commissionPct: 10.0
        );
        $this->assertFalse((bool) $clean->is_fraud_detected);
        $this->assertEquals('PENDING', $clean->payout_status);
        $this->assertEquals(150.0, (float) $clean->commission_amount_usd);

        // 2. Self-dealing circular referral fraud detected & held (260.5 Edge Case)
        $fraud = $this->service->trackAffiliateReferral(
            affiliateId: 'AFF-SHADY-DEALER',
            buyerId: 'AFF-SHADY-DEALER', // Self-dealing!
            saleAmountUsd: 5000.0,
            commissionPct: 10.0
        );
        $this->assertTrue((bool) $fraud->is_fraud_detected);
        $this->assertEquals('HELD_FOR_INVESTIGATION', $fraud->payout_status);
    }

    public function test_bulk_affiliate_payout_processing_and_fraud_isolation(): void
    {
        $ref1 = $this->service->trackAffiliateReferral('AFF-1', 'BUYER-A', 1000.0, 10.0); // Commission: 100
        $ref2 = $this->service->trackAffiliateReferral('AFF-2', 'BUYER-B', 2000.0, 10.0); // Commission: 200
        $fraud = $this->service->trackAffiliateReferral('AFF-3', 'AFF-3', 500.0, 10.0); // Shady: 50 (held)

        $bulkResult = $this->service->processBulkPayouts([(int) $ref1->id, (int) $ref2->id, (int) $fraud->id]);

        // Only 2 clean referrals paid; fraud referral excluded (260.3 & 260.4)
        $this->assertEquals(2, $bulkResult['payouts_count']);
        $this->assertEquals(300.0, (float) $bulkResult['total_bulk_payout_usd']);

        $this->assertEquals('HELD_FOR_INVESTIGATION', DB::table('partner_affiliate_referrals')->find($fraud->id)->payout_status);
    }

    public function test_partner_ecosystem_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerPartner('PTN-AUD-1', 'Audited Partner', 100000.0);
        $this->service->registerCoSellDeal('CSL-AUD-1', 'PTN-AUD-1', 50000.0);
        $cleanRef = $this->service->trackAffiliateReferral('AFF-OK', 'BUYER-OK', 100.0);
        $this->service->processBulkPayouts([(int) $cleanRef->id]);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: fraudulent referral marked PAID
        DB::table('partner_affiliate_referrals')->insert([
            'referral_code' => 'REF-FRAUD-PAID',
            'affiliate_id' => 'ROGUE',
            'buyer_id' => 'ROGUE',
            'sale_amount_usd' => 1000.0,
            'commission_amount_usd' => 100.0,
            'is_fraud_detected' => true,
            'payout_status' => 'PAID', // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
