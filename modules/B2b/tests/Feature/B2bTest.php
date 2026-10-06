<?php

namespace Modules\B2b\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\B2b\Application\Services\B2bService;
use Modules\B2b\Domain\Models\B2bEscrowAccount;
use Modules\B2b\Domain\Models\B2bRfq;
use Modules\B2b\Domain\Models\SurplusAuction;
use Modules\B2b\Domain\Models\WholesaleCatalog;
use Tests\TestCase;

class B2bTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_wholesale_catalog_item_and_rfq(): void
    {
        $service = app(B2bService::class);

        $catalog = $service->createWholesaleItem([
            'sku' => 'SKU-FILTER-99',
            'vendor_id' => 'VENDOR-INDOCEMENT',
            'product_name' => 'Industrial Hydraulic Filter Pack',
            'category' => 'Heavy Machinery Spareparts',
            'base_price_idr' => 2500000,
            'min_order_qty' => 10,
            'tiered_pricing_matrix' => [
                ['min_qty' => 50, 'price_idr' => 2200000],
            ],
            'available_stock' => 500,
        ]);

        $this->assertInstanceOf(WholesaleCatalog::class, $catalog);

        $rfq = $service->submitRfq([
            'buyer_id' => 'BUYER-CONSTRUCTION-CORP',
            'catalog_id' => $catalog->id,
            'requested_quantity' => 100,
            'target_price_idr' => 2000000,
            'payment_terms' => 'TOP_60',
        ]);

        $this->assertInstanceOf(B2bRfq::class, $rfq);
        $this->assertEquals('open', $rfq->status);

        $quoted = $service->respondToRfq($rfq->id, 2100000, 'TOP_45', 'Best bulk volume rate');
        $this->assertEquals('quoted', $quoted->status);
        $this->assertEquals(2100000, $quoted->target_price_idr);

        $accepted = $service->acceptRfq($rfq->id);
        $this->assertEquals('accepted', $accepted->status);
    }

    public function test_can_create_and_bid_surplus_auction(): void
    {
        $service = app(B2bService::class);

        $auction = $service->createAuction([
            'lot_number' => 'AUC-MACH-01',
            'seller_id' => 'PLANT-WEST-JAVA',
            'asset_type' => 'factory_machine',
            'title' => 'Used CNC Lathe Machine 2021',
            'starting_bid_idr' => 50000000,
            'reserve_price_idr' => 60000000,
            'bid_increment_idr' => 2000000,
        ]);

        $this->assertInstanceOf(SurplusAuction::class, $auction);

        // Place valid bid
        $updated = $service->placeAuctionBid($auction->id, 'BIDDER-METAL-CORP', 55000000);
        $this->assertEquals(55000000, $updated->current_highest_bid_idr);
        $this->assertEquals('BIDDER-METAL-CORP', $updated->winning_bidder_id);

        // Invalid bid below increment
        $this->expectException(\InvalidArgumentException::class);
        $service->placeAuctionBid($auction->id, 'BIDDER-OTHER', 56000000);
    }

    public function test_can_lock_and_release_escrow(): void
    {
        $service = app(B2bService::class);

        $escrow = $service->lockEscrowDeposit([
            'reference_type' => 'auction_settlement',
            'reference_id' => 'AUC-MACH-01',
            'buyer_id' => 'BIDDER-METAL-CORP',
            'seller_id' => 'PLANT-WEST-JAVA',
            'deposit_amount_idr' => 55000000,
        ]);

        $this->assertInstanceOf(B2bEscrowAccount::class, $escrow);
        $this->assertEquals('held', $escrow->status);

        $released = $service->releaseEscrow($escrow->id, 'BAST-DOC-2026-99');
        $this->assertEquals('released', $released->status);
        $this->assertEquals(55000000, $released->released_amount_idr);
        $this->assertNotNull($released->released_at);
    }

    public function test_b2b_audit_passes_with_zero_discrepancy(): void
    {
        $service = app(B2bService::class);

        $audit = $service->auditB2b();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEmpty($audit['discrepancies']);
    }

    public function test_b2b_web_index_accessible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('b2b.index'));
        $response->assertStatus(200);
        $response->assertSee('B2B Wholesale Marketplace & Surplus Asset Auction');
    }
}
