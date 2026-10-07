<?php

declare(strict_types=1);

namespace Modules\Rwa\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
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

        LedgerAccount::create([
            'code' => "wallet:user:{$this->user1->id}:IDR",
            'name' => "User {$this->user1->id} Wallet",
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => "wallet:user:{$this->user2->id}:IDR",
            'name' => "User {$this->user2->id} Wallet",
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'rwa:pool:RWA-MALL01:IDR',
            'name' => 'RWA Revenue Pool Mall 01',
            'asset_code' => 'IDR',
            'kind' => 'asset',
            'allow_negative' => true,
            'cached_balance' => '100000000',
        ]);

        LedgerAccount::create([
            'code' => 'rwa:rounding_reserve:IDR',
            'name' => 'RWA Rounding Reserve',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
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
}
