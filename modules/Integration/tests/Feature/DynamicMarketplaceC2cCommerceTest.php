<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DynamicMarketplaceC2cCommerceService;
use Tests\TestCase;

class DynamicMarketplaceC2cCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected DynamicMarketplaceC2cCommerceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DynamicMarketplaceC2cCommerceService::class);
    }

    public function test_c2c_listing_velocity_and_identity_verification_rule(): void
    {
        // 1. Single listing by unverified seller succeeds (313.1)
        $listing = $this->service->createListing('LIST-IPHONE-01', 'SELLER_ALICE', 'ELECTRONICS', 800.0, false, 1);
        $this->assertTrue((bool) $listing->is_active);

        // 2. High velocity seller (> 3 active listings) without identity verification is rejected (313.5 Edge Case)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trust & safety violation: High velocity sellers');
        $this->service->createListing('LIST-SUSPICIOUS-02', 'SELLER_BOB', 'ELECTRONICS', 900.0, false, 5);
    }

    public function test_escrow_locking_dispute_and_conditional_release(): void
    {
        // 1. Lock funds in escrow (313.1 & 313.4)
        $escrow = $this->service->lockEscrowFunds('ESC-CAR-01', 'LIST-CAR-01', 12000.0, 'BUYER_CHARLIE', 'SELLER_DAVE');
        $this->assertFalse((bool) $escrow->funds_released);
        $this->assertFalse((bool) $escrow->is_disputed);

        // 2. Flag dispute (313.3 & 313.6 Risk)
        $this->service->disputeEscrow('ESC-CAR-01');

        // 3. Releasing funds while dispute is unresolved throws exception (313.4 & 313.6)
        try {
            $this->service->releaseEscrowFunds('ESC-CAR-01', false);
            $this->fail('Expected exception for premature escrow release');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot release funds while dispute is active without formal resolution', $e->getMessage());
        }

        // 4. Resolve dispute and release funds (313.4)
        $released = $this->service->releaseEscrowFunds('ESC-CAR-01', true);
        $this->assertTrue((bool) $released->funds_released);
        $this->assertTrue((bool) $released->is_dispute_resolved);
    }

    public function test_c2b_telematics_buyback_pricing(): void
    {
        // Deterministic buyback calculation: $20,000 * (90/100) * 0.85 = $15,300 (313.2 & 313.4)
        $offer = $this->service->calculateBuybackOffer('OFFER-EV-BATTERY-01', 'SKU-BATTERY-PACK-60KWH', 90.0, 20000.0);
        $this->assertEquals(15300.0, (float) $offer->calculated_buyback_price_usd);
    }

    public function test_marketplace_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->createListing('LIST-AUD', 'S1', 'CAT', 100.0, true, 1);
        $this->service->lockEscrowFunds('ESC-AUD', 'LIST-AUD', 100.0, 'B1', 'S1');
        $this->service->calculateBuybackOffer('OFF-AUD', 'SKU-1', 80.0, 100.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unverified high velocity active listing
        DB::table('c2c_marketplace_listings')->insert([
            'listing_code' => 'LIST-SPAMMER',
            'seller_id' => 'SPAMMER_01',
            'category' => 'ELECTRONICS',
            'asking_price_usd' => 500.0,
            'identity_verified' => false, // Discrepancy!
            'active_listing_velocity_count' => 6, // > 3
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
