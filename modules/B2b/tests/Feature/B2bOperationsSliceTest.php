<?php

declare(strict_types=1);

namespace Modules\B2b\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\B2b\Application\Actions\PublishWholesaleTierCatalogAction;
use Modules\B2b\Application\Actions\RunSurplusAssetDutchAuctionAction;
use Modules\B2b\Application\Actions\SettleMultiPartyB2bEscrowViaLedgerAction;
use Modules\B2b\Domain\Models\B2bEscrowAccount;
use Modules\B2b\Domain\Models\SurplusAuction;
use Modules\B2b\Domain\Models\WholesaleCatalog;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Models\LedgerAccount;
use Tests\TestCase;

class B2bOperationsSliceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_publish_wholesale_tier_catalog_and_price_calculation(): void
    {
        $action = app(PublishWholesaleTierCatalogAction::class);

        $catalog = $action->execute([
            'vendor_id' => 'VND-KRAKATAU-STEEL',
            'sku' => 'SKU-STEEL-BEAM-H',
            'product_name' => 'Structural H-Beam 200x200',
            'category' => 'Raw Materials',
            'base_price_idr' => 5000000,
            'min_order_qty' => 5,
            'tiered_pricing_matrix' => [
                ['min_qty' => 10, 'price_idr' => 4800000],
                ['min_qty' => 50, 'price_idr' => 4500000],
            ],
            'available_stock' => 1000,
        ]);

        $this->assertInstanceOf(WholesaleCatalog::class, $catalog);
        $this->assertEquals(5000000, $catalog->base_price_idr);
        $this->assertCount(2, $catalog->tiered_pricing_matrix);

        // Price at base tier (5 units)
        $price5 = $action->calculateUnitPrice($catalog, 5);
        $this->assertEquals(5000000, $price5);

        // Price at tier 1 (15 units)
        $price15 = $action->calculateUnitPrice($catalog, 15);
        $this->assertEquals(4800000, $price15);

        // Price at tier 2 (60 units)
        $price60 = $action->calculateUnitPrice($catalog, 60);
        $this->assertEquals(4500000, $price60);
    }

    public function test_publish_wholesale_tier_catalog_validates_inputs(): void
    {
        $action = app(PublishWholesaleTierCatalogAction::class);

        $this->expectException(InvalidArgumentException::class);
        $action->execute([
            'vendor_id' => 'VND-01',
            'sku' => 'SKU-01',
            'product_name' => 'Bad Item',
            'base_price_idr' => -1000,
        ]);
    }

    public function test_create_and_decay_surplus_dutch_auction(): void
    {
        $action = app(RunSurplusAssetDutchAuctionAction::class);
        $startTime = Carbon::parse('2026-10-23 08:00:00');
        $endTime = Carbon::parse('2026-10-23 18:00:00'); // 10 hours

        $auction = $action->createDutchAuction([
            'lot_number' => 'LOT-FORKLIFT-5T',
            'seller_id' => 'PLANT-GRESIK',
            'title' => 'Toyota 5-Ton Diesel Forklift 2022',
            'starting_bid_idr' => 200000000,
            'reserve_price_idr' => 150000000,
            'starts_at' => $startTime,
            'ends_at' => $endTime,
        ]);

        $this->assertInstanceOf(SurplusAuction::class, $auction);
        $this->assertEquals('DUTCH', $auction->auction_type);
        $this->assertEquals('active', $auction->status);

        // At start time, price is starting bid
        $priceStart = $action->calculateCurrentDutchPrice($auction, $startTime);
        $this->assertEquals(200000000, $priceStart);

        // Halfway through (5 hours in), price should be midpoint: 175,000,000 IDR
        $midpoint = Carbon::parse('2026-10-23 13:00:00');
        $priceMid = $action->calculateCurrentDutchPrice($auction, $midpoint);
        $this->assertEquals(175000000, $priceMid);

        // At or after end time, price hits reserve floor
        $priceEnd = $action->calculateCurrentDutchPrice($auction, $endTime);
        $this->assertEquals(150000000, $priceEnd);
    }

    public function test_accept_dutch_auction_at_decayed_price(): void
    {
        $action = app(RunSurplusAssetDutchAuctionAction::class);
        $startTime = Carbon::parse('2026-10-23 08:00:00');
        $endTime = Carbon::parse('2026-10-23 18:00:00');

        $auction = $action->createDutchAuction([
            'lot_number' => 'LOT-GENERATOR-500KVA',
            'seller_id' => 'PLANT-MEDAN',
            'title' => 'Cummins 500kVA Standby Generator',
            'starting_bid_idr' => 400000000,
            'reserve_price_idr' => 300000000,
            'starts_at' => $startTime,
            'ends_at' => $endTime,
        ]);

        $asOf = Carbon::parse('2026-10-23 13:00:00'); // Midpoint price: 350,000,000 IDR
        $awarded = $action->acceptDutchPrice($auction, 'BUYER-MINING-SERVICES', null, $asOf);

        $this->assertEquals('awarded', $awarded->status);
        $this->assertEquals('BUYER-MINING-SERVICES', $awarded->winning_bidder_id);
        $this->assertEquals(350000000, $awarded->current_highest_bid_idr);
    }

    public function test_lock_and_settle_multi_party_escrow_via_ledger(): void
    {
        $action = app(SettleMultiPartyB2bEscrowViaLedgerAction::class);
        $ledger = app(LedgerService::class);

        $escrow = $action->lockEscrow([
            'reference_type' => 'auction_settlement',
            'reference_id' => 'LOT-FORKLIFT-5T',
            'buyer_id' => 'BUYER-CONSTRUCTION-TBK',
            'seller_id' => 'PLANT-GRESIK',
            'deposit_amount_idr' => 175000000,
        ]);

        $this->assertInstanceOf(B2bEscrowAccount::class, $escrow);
        $this->assertEquals('held', $escrow->status);
        $this->assertEquals(175000000, $escrow->deposit_amount_idr);

        // Verify ledger held balance
        $escrowAccount = LedgerAccount::where('code', 'escrow:b2b:orders:IDR')->first();
        $this->assertNotNull($escrowAccount);
        $this->assertEquals('175000000', $escrowAccount->cached_balance);

        // Release escrow upon BAST delivery
        $released = $action->settleAndRelease($escrow->id, 'BAST-2026-FL-001');
        $this->assertEquals('released', $released->status);
        $this->assertEquals(175000000, $released->released_amount_idr);
        $this->assertEquals('BAST-2026-FL-001', $released->bast_document_id);

        // Escrow account should now be 0, seller payable 175,000,000
        $escrowAccount->refresh();
        $this->assertEquals('0', $escrowAccount->cached_balance);

        $sellerAccount = LedgerAccount::where('code', 'ap:b2b:seller_payable:IDR')->first();
        $this->assertNotNull($sellerAccount);
        $this->assertEquals('175000000', $sellerAccount->cached_balance);
    }

    public function test_b2b_operations_controller_views_and_actions(): void
    {
        $user = User::factory()->create(['role' => 'b2b_buyer']);

        // Dashboard
        $resDash = $this->actingAs($user)->get(route('b2b.dashboard'));
        $resDash->assertStatus(200);
        $resDash->assertSee('B2B Commerce');
        $resDash->assertSee('Control Center');

        // Wholesale Index & Publish
        $resWholesale = $this->actingAs($user)->get(route('b2b.wholesale.index'));
        $resWholesale->assertStatus(200);

        $resPostWholesale = $this->actingAs($user)->post(route('b2b.wholesale.publish'), [
            'vendor_id' => 'VND-TEST-01',
            'sku' => 'SKU-VALVE-DN50',
            'product_name' => 'Stainless Steel Ball Valve DN50',
            'category' => 'Valves',
            'base_price_idr' => 1250000,
            'min_order_qty' => 10,
            'available_stock' => 200,
        ]);
        $resPostWholesale->assertRedirect(route('b2b.wholesale.index'));
        $this->assertDatabaseHas('b2b_wholesale_catalogs', ['sku' => 'SKU-VALVE-DN50']);

        // Auction Index & Create
        $resAuction = $this->actingAs($user)->get(route('b2b.auction.index'));
        $resAuction->assertStatus(200);

        $resPostAuction = $this->actingAs($user)->post(route('b2b.auction.create'), [
            'lot_number' => 'LOT-PUMP-TEST',
            'seller_id' => 'SELLER-01',
            'title' => 'Industrial Centrifugal Pump',
            'starting_bid_idr' => 80000000,
            'reserve_price_idr' => 50000000,
        ]);
        $resPostAuction->assertRedirect(route('b2b.auction.index'));
        $this->assertDatabaseHas('b2b_auctions', ['lot_number' => 'LOT-PUMP-TEST']);

        // Escrow Index & Lock
        $resEscrow = $this->actingAs($user)->get(route('b2b.escrow.index'));
        $resEscrow->assertStatus(200);

        $resPostEscrow = $this->actingAs($user)->post(route('b2b.escrow.lock'), [
            'reference_type' => 'auction_settlement',
            'reference_id' => 'LOT-PUMP-TEST',
            'buyer_id' => 'BUYER-01',
            'seller_id' => 'SELLER-01',
            'deposit_amount_idr' => 60000000,
        ]);
        $resPostEscrow->assertRedirect(route('b2b.escrow.index'));
        $this->assertDatabaseHas('b2b_escrow_accounts', ['reference_id' => 'LOT-PUMP-TEST']);
    }
}
