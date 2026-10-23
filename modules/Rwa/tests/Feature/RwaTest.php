<?php

declare(strict_types=1);

namespace Modules\Rwa\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Rwa\Application\Services\RwaOrderbookService;
use Modules\Rwa\Application\Services\RwaTokenService;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;
use Tests\TestCase;

class RwaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;

    protected User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();

        $this->seed(LedgerAccountsSeeder::class);
        $this->user1->walletAccount('IDR');
        $this->user2->walletAccount('IDR');

        $pool = LedgerAccount::where('code', 'rwa:pool:RWA-MALL01:IDR')->first();
        $pool->forceFill(['cached_balance' => '100000000'])->save();
    }

    public function test_rwa_issuance_and_holdings_sum_equals_supply(): void
    {
        /** @var RwaTokenService $service */
        $service = app(RwaTokenService::class);

        $asset = $service->issueAssetToken(
            symbol: 'RWA-MALL01',
            name: 'Duta Mall Atrium Unit 101',
            underlyingType: 'mall_unit',
            underlyingId: 'UNT-101',
            appraisalValueIdr: 1000000000,
            totalSupplyTokens: 10000
        );

        $this->assertEquals(100000, $asset->token_price_idr);
        $this->assertEquals(10000, $asset->total_supply_tokens);

        // Allocate tokens: 6000 to user1, 4000 to user2
        $service->allocateTokens($asset, $this->user1->id, 6000);
        $service->allocateTokens($asset, $this->user2->id, 4000);

        $totalHeld = RwaHolding::where('rwa_asset_id', $asset->id)->sum('token_balance');
        $this->assertEquals($asset->total_supply_tokens, $totalHeld);

        // Allocating more should fail
        $this->expectException(\InvalidArgumentException::class);
        $service->allocateTokens($asset, $this->user1->id, 1);
    }

    public function test_rwa_daily_dividend_pro_rata_distribution_and_rounding(): void
    {
        /** @var RwaTokenService $service */
        $service = app(RwaTokenService::class);

        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-MALL01',
            'name' => 'Duta Mall Atrium Unit 101',
            'underlying_asset_type' => 'mall_unit',
            'underlying_asset_id' => 'UNT-101',
            'appraisal_value_idr' => 1000000000,
            'total_supply_tokens' => 10000,
            'token_price_idr' => 100000,
            'status' => 'active',
        ]);

        RwaHolding::create(['rwa_asset_id' => $asset->id, 'user_id' => $this->user1->id, 'token_balance' => 6000]);
        RwaHolding::create(['rwa_asset_id' => $asset->id, 'user_id' => $this->user2->id, 'token_balance' => 4000]);

        // Revenue pool: 10,000,005 IDR
        // User1 (60%): 6,000,003 IDR
        // User2 (40%): 4,000,002 IDR
        // Total distributed: 10,000,005 IDR (exact)
        $dividend = $service->distributeDailyDividend($asset, 10000005, '2026-10-07');

        $this->assertEquals(10000005, $dividend->distributed_idr + $dividend->rounding_reserve_idr);
        $this->assertEquals(10000005, $dividend->total_revenue_pool_idr);

        // Idempotency check: same distribution key should return existing
        $dupDividend = $service->distributeDailyDividend($asset, 10000005, '2026-10-07');
        $this->assertEquals($dividend->id, $dupDividend->id);
    }

    public function test_rwa_token_redemption_decreases_supply_consistently(): void
    {
        /** @var RwaTokenService $service */
        $service = app(RwaTokenService::class);

        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-MALL01',
            'name' => 'Duta Mall Atrium Unit 101',
            'underlying_asset_type' => 'mall_unit',
            'underlying_asset_id' => 'UNT-101',
            'appraisal_value_idr' => 1000000000,
            'total_supply_tokens' => 10000,
            'token_price_idr' => 100000,
            'status' => 'active',
        ]);

        $holding = RwaHolding::create(['rwa_asset_id' => $asset->id, 'user_id' => $this->user1->id, 'token_balance' => 2000]);

        $service->redeemTokens($asset, $this->user1->id, 500);

        $holding->refresh();
        $asset->refresh();

        $this->assertEquals(1500, $holding->token_balance);
        $this->assertEquals(9500, $asset->total_supply_tokens);
    }

    public function test_rwa_orderbook_double_settlement_rejected_and_kyc_guardrail(): void
    {
        /** @var RwaOrderbookService $orderbookService */
        $orderbookService = app(RwaOrderbookService::class);

        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-OB01',
            'name' => 'Logistics Cold Fleet 01',
            'underlying_asset_type' => 'fleet_unit',
            'underlying_asset_id' => 'FLT-01',
            'appraisal_value_idr' => 500000000,
            'total_supply_tokens' => 5000,
            'token_price_idr' => 100000,
            'status' => 'active',
            'max_holder_concentration_pct' => 25.0,
        ]);

        $holdingUser1 = RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->user1->id,
            'token_balance' => 1000,
            'is_kyc_verified' => true,
        ]);

        $holdingUser2 = RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->user2->id,
            'token_balance' => 0,
            'is_kyc_verified' => true,
        ]);

        $user2Wallet = $this->user2->walletAccount('IDR');
        $user2Wallet->forceFill(['cached_balance' => '50000000'])->save();

        // 1. User1 places sell order of 200 tokens @ Rp 100,000
        $sellOrder = $orderbookService->placeOrder($asset, $this->user1->id, 'sell', 200, 100000);
        $this->assertSame('open', $sellOrder->status);

        // 2. User2 places buy order of 200 tokens @ Rp 100,000 -> Should match immediately
        $buyOrder = $orderbookService->placeOrder($asset, $this->user2->id, 'buy', 200, 100000);

        $sellOrder->refresh();
        $buyOrder->refresh();
        $holdingUser1->refresh();
        $holdingUser2->refresh();

        $this->assertSame('filled', $sellOrder->status);
        $this->assertSame('filled', $buyOrder->status);
        $this->assertEquals(800, $holdingUser1->token_balance);
        $this->assertEquals(200, $holdingUser2->token_balance);

        // Double matching cannot happen because both orders are filled
        $orderbookService->matchOrders($asset);
        $holdingUser1->refresh();
        $holdingUser2->refresh();
        $this->assertEquals(800, $holdingUser1->token_balance);
        $this->assertEquals(200, $holdingUser2->token_balance);

        // Non-KYC user is rejected
        $unverifiedUser = User::factory()->create();
        RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $unverifiedUser->id,
            'token_balance' => 100,
            'is_kyc_verified' => false,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $orderbookService->placeOrder($asset, $unverifiedUser->id, 'buy', 50, 100000);
    }

    public function test_rwa_reconcile_asset_ledger_equals_holdings(): void
    {
        /** @var RwaTokenService $service */
        $service = app(RwaTokenService::class);

        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-AUDIT01',
            'name' => 'Manufacturing Unit C',
            'underlying_asset_type' => 'mfg_unit',
            'underlying_asset_id' => 'MFG-01',
            'appraisal_value_idr' => 2000000000,
            'total_supply_tokens' => 20000,
            'token_price_idr' => 100000,
            'status' => 'active',
        ]);

        RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->user1->id,
            'token_balance' => 12000,
        ]);
        RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->user2->id,
            'token_balance' => 8000,
        ]);

        $audit = $service->auditRwa();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancies']);
    }
}
