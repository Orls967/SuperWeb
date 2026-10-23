<?php

declare(strict_types=1);

namespace Modules\Rwa\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Rwa\Application\Actions\DistributeProRataRwaRentalYieldAction;
use Modules\Rwa\Application\Actions\MatchSecondaryRwaOrderbookTradeAction;
use Modules\Rwa\Application\Actions\SubscribePrimaryRwaEscrowOfferingAction;
use Modules\Rwa\Application\Actions\TokenizeRealWorldAssetSupplyAction;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Domain\Models\RwaOffering;
use Modules\Rwa\Domain\Models\RwaOrderbookOrder;
use Tests\TestCase;

class RwaEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokenize_real_world_asset_calculates_integer_token_price(): void
    {
        $action = app(TokenizeRealWorldAssetSupplyAction::class);

        // Appraisal: 10,000,000,000 IDR (10M IDR), Supply: 100,000 tokens => 100,000 IDR / token
        $result = $action->handle(
            'RWA-BLDG-01',
            'Menara Sudirman Floor 15',
            'COMMERCIAL_REAL_ESTATE',
            'BLDG-SUD-15',
            'DOC-APPRAISAL-2026-99',
            10000000000,
            100000,
            15.0
        );

        $this->assertEquals(100000, $result['token_price_idr']);
        $this->assertEquals(100000, $result['total_supply']);
        $this->assertEquals('RWA-BLDG-01', $result['asset']->token_symbol);
        $this->assertDatabaseHas('rwa_assets', [
            'token_symbol' => 'RWA-BLDG-01',
            'token_price_idr' => 100000,
        ]);
    }

    public function test_subscribe_primary_rwa_offering_credits_holding_and_enforces_lockup(): void
    {
        $user = User::factory()->create();
        $tokenizeAction = app(TokenizeRealWorldAssetSupplyAction::class);
        $asset = $tokenizeAction->handle('RWA-VILLA-01', 'Bali Luxury Villa', 'HOSPITALITY', 'VIL-01', 'REF-01', 5000000000, 50000)['asset'];

        $offering = RwaOffering::create([
            'rwa_asset_id' => $asset->id,
            'offering_code' => 'OFF-VILLA-01',
            'tokens_offered' => 10000,
            'tokens_sold' => 0,
            'price_per_token_idr' => 100000,
            'min_lot_size' => 10,
            'lockup_days' => 90,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        $action = app(SubscribePrimaryRwaEscrowOfferingAction::class);
        $result = $action->handle($offering->id, $user->id, 50);

        $this->assertEquals(50, $result['tokens_subscribed']);
        $this->assertEquals(5000000, $result['total_cost_idr']);
        $this->assertEquals(50, $result['holding']->locked_balance);
        $this->assertEquals(0, $result['holding']->token_balance); // locked for 90 days
        $this->assertEquals(50, $offering->fresh()->tokens_sold);
    }

    public function test_cannot_subscribe_when_offering_has_insufficient_tokens(): void
    {
        $user = User::factory()->create();
        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-SM-01',
            'name' => 'Small Asset',
            'underlying_asset_type' => 'EQUIPMENT',
            'underlying_asset_id' => 'EQ-01',
            'appraisal_document_ref' => 'REF-02',
            'appraisal_value_idr' => 100000000,
            'total_supply_tokens' => 1000,
            'token_price_idr' => 100000,
            'status' => 'active',
        ]);

        $offering = RwaOffering::create([
            'rwa_asset_id' => $asset->id,
            'offering_code' => 'OFF-SM-01',
            'tokens_offered' => 100,
            'tokens_sold' => 90,
            'price_per_token_idr' => 100000,
            'min_lot_size' => 10,
            'lockup_days' => 0,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        $action = app(SubscribePrimaryRwaEscrowOfferingAction::class);

        $this->expectException(\DomainException::class);
        $action->handle($offering->id, $user->id, 20); // only 10 remaining
    }

    public function test_match_secondary_rwa_orderbook_trade_transfers_tokens_between_holdings(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-TRD-01',
            'name' => 'Trade Asset',
            'underlying_asset_type' => 'LOGISTICS',
            'underlying_asset_id' => 'LOG-01',
            'appraisal_document_ref' => 'REF-03',
            'appraisal_value_idr' => 100000000,
            'total_supply_tokens' => 1000,
            'token_price_idr' => 100000,
            'status' => 'active',
        ]);

        // Seller has 100 unlocked tokens
        $sellerHolding = RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $seller->id,
            'is_kyc_verified' => true,
            'token_balance' => 100,
            'locked_balance' => 0,
        ]);

        $sellOrder = RwaOrderbookOrder::create([
            'order_code' => 'ORD-SELL-01',
            'rwa_asset_id' => $asset->id,
            'user_id' => $seller->id,
            'side' => 'SELL',
            'quantity' => 20,
            'filled_quantity' => 0,
            'price_per_token_idr' => 105000,
            'status' => 'OPEN',
        ]);

        $buyOrder = RwaOrderbookOrder::create([
            'order_code' => 'ORD-BUY-01',
            'rwa_asset_id' => $asset->id,
            'user_id' => $buyer->id,
            'side' => 'BUY',
            'quantity' => 20,
            'filled_quantity' => 0,
            'price_per_token_idr' => 105000,
            'status' => 'OPEN',
        ]);

        $action = app(MatchSecondaryRwaOrderbookTradeAction::class);
        $result = $action->handle($buyOrder->id, $sellOrder->id);

        $this->assertEquals(20, $result['matched_qty']);
        $this->assertEquals(2100000, $result['total_trade_value_idr']);
        $this->assertEquals('FILLED', $buyOrder->fresh()->status);
        $this->assertEquals('FILLED', $sellOrder->fresh()->status);
        $this->assertEquals(80, $sellerHolding->fresh()->token_balance);
        $this->assertEquals(20, RwaHolding::where('rwa_asset_id', $asset->id)->where('user_id', $buyer->id)->first()->token_balance);
    }

    public function test_distribute_pro_rata_rwa_rental_yield_distributes_all_funds_with_rounding_reserve(): void
    {
        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-YLD-01',
            'name' => 'Yield Asset',
            'underlying_asset_type' => 'LOGISTICS',
            'underlying_asset_id' => 'YLD-01',
            'appraisal_document_ref' => 'REF-04',
            'appraisal_value_idr' => 1000000000,
            'total_supply_tokens' => 1000,
            'token_price_idr' => 1000000,
            'status' => 'active',
        ]);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $u1->id,
            'is_kyc_verified' => true,
            'token_balance' => 400, // 40%
        ]);
        RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $u2->id,
            'is_kyc_verified' => true,
            'token_balance' => 600, // 60%
        ]);

        $action = app(DistributeProRataRwaRentalYieldAction::class);
        $result = $action->handle($asset->id, 10000000); // 10M pool

        $this->assertEquals(10000000, $result['total_pool_idr']);
        $this->assertEquals(10000000, $result['distributed_idr']);
        $this->assertEquals(0, $result['rounding_reserve_idr']);
        $this->assertEquals(2, $result['recipient_count']);
    }

    public function test_rwa_exchange_http_routes_render_and_handle_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('rwa.exchange.offerings'));
        $response->assertStatus(200);
        $response->assertSee('RWA Primary Escrow Offerings');

        $response = $this->actingAs($admin)->get(route('rwa.exchange.orderbook'));
        $response->assertStatus(200);
        $response->assertSee('RWA Secondary Market Orderbook');

        $response = $this->actingAs($admin)->get(route('rwa.exchange.yield-distribution'));
        $response->assertStatus(200);
        $response->assertSee('RWA Rental Yield');

        $response = $this->actingAs($admin)->get(route('rwa.exchange.holdings'));
        $response->assertStatus(200);
        $response->assertSee('Buku Kepemilikan Investor RWA');
    }
}
