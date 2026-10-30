<?php

declare(strict_types=1);

namespace Tests\Acceptance\FinAsset\Eksplorasi;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Rwa\Application\Actions\DistributeProRataRwaRentalYieldAction;
use Modules\Rwa\Application\Actions\MatchSecondaryRwaOrderbookTradeAction;
use Modules\Rwa\Application\Actions\SubscribePrimaryRwaEscrowOfferingAction;
use Modules\Rwa\Application\Actions\TokenizeRealWorldAssetSupplyAction;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Domain\Models\RwaOffering;
use Modules\Rwa\Domain\Models\RwaOrderbookOrder;
use Modules\Rwa\Ledger\RwaAccounts;
use Tests\TestCase;

/**
 * L4.0 Bukti Kondisi Kode Lama: Eksplorasi Modul RWA (§2F Slice 1).
 *
 * Klasifikasi Action:
 * - TokenizeRealWorldAssetSupplyAction: SEBAGIAN (auto-approves to active, no 4-eyes approval, no issuer KYB, float math)
 * - SubscribePrimaryRwaEscrowOfferingAction: SEBAGIAN (hardcoded is_kyc_verified=true, no ledger posting, FCFS not Hamilton)
 * - DistributeProRataRwaRentalYieldAction: SALAH (manual pool input, floor division dust, no wallet ledger posting)
 * - MatchSecondaryRwaOrderbookTradeAction: SEBAGIAN (in-place balance mutation, no append-only transfer log, no ledger cash trade)
 */
class RwaLegacyExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected User $investor1;

    protected User $investor2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->investor1 = User::factory()->create();
        $this->investor2 = User::factory()->create();
        $this->investor1->walletAccount('IDR');
        $this->investor2->walletAccount('IDR');
    }

    public function test_legacy_tables_exist_and_missing_tables_identified(): void
    {
        // Existing tables in legacy schema
        expect(Schema::hasTable('rwa_assets'))->toBeTrue();
        expect(Schema::hasTable('rwa_offerings'))->toBeTrue();
        expect(Schema::hasTable('rwa_holdings'))->toBeTrue();
        expect(Schema::hasTable('rwa_orderbook_orders'))->toBeTrue();
        expect(Schema::hasTable('rwa_dividends'))->toBeTrue();

        // Extended tables required by KONSEP §2F and R-1..R-5
        expect(Schema::hasTable('rwa_investor_verifications'))->toBeTrue(); // R-2 KYC verification registry
        expect(Schema::hasTable('rwa_offering_orders'))->toBeTrue();       // R-3 Hamilton offering orders
        expect(Schema::hasTable('rwa_token_transfers'))->toBeTrue();       // PR5 append-only transfer log
    }

    public function test_legacy_ledger_accounts_allow_negative_violating_u3(): void
    {
        $definitions = RwaAccounts::definitions();
        expect($definitions)->not->toBeEmpty();

        // U-3 requires allow_negative = false except clearing/escrow
        $escrowOrClearing = array_filter(
            $definitions,
            fn ($d) => str_contains($d['code'], 'clearing') || str_contains($d['code'], 'escrow')
        );
        $nonEscrow = array_filter(
            $definitions,
            fn ($d) => ! str_contains($d['code'], 'clearing') && ! str_contains($d['code'], 'escrow')
        );

        foreach ($nonEscrow as $def) {
            expect($def['allow_negative'])->toBeFalse();
        }
        foreach ($escrowOrClearing as $def) {
            expect($def['allow_negative'])->toBeTrue();
        }
    }

    public function test_tokenize_action_status_sebagian(): void
    {
        $action = app(TokenizeRealWorldAssetSupplyAction::class);
        $res = $action->handle(
            tokenSymbol: 'RWA-EXP01',
            name: 'Exploration Mall Tower',
            underlyingType: 'mall_unit',
            underlyingId: 'ML-001',
            appraisalDocRef: 'DOC-APP-001',
            appraisalValueIdr: 1_000_000_000,
            totalSupplyTokens: 100_000,
            maxHolderConcentrationPct: 20.0
        );

        expect($res['asset'])->toBeInstanceOf(RwaAsset::class);
        expect($res['token_price_idr'])->toBe(10_000);

        // Weakness: auto-approves to 'active' bypassing draft -> 4-eyes approval -> issued
        expect($res['asset']->status)->toBe('active');
        expect($res['asset']->approved_at)->not->toBeNull();
    }

    public function test_subscribe_offering_action_status_sebagian(): void
    {
        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-OFF01',
            'name' => 'Offering Asset',
            'underlying_asset_type' => 'mall_unit',
            'underlying_asset_id' => 'ML-002',
            'appraisal_value_idr' => 500_000_000,
            'total_supply_tokens' => 50_000,
            'token_price_idr' => 10_000,
            'status' => 'active',
        ]);

        $offering = RwaOffering::create([
            'rwa_asset_id' => $asset->id,
            'offering_code' => 'OFF-001',
            'tokens_offered' => 10_000,
            'tokens_sold' => 0,
            'price_per_token_idr' => 10_000,
            'min_lot_size' => 10,
            'lockup_days' => 0,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(7),
            'status' => 'active',
        ]);

        $action = app(SubscribePrimaryRwaEscrowOfferingAction::class);
        $res = $action->handle(
            offeringId: $offering->id,
            userId: $this->investor1->id,
            quantityTokens: 100
        );

        expect($res['holding'])->toBeInstanceOf(RwaHolding::class);
        expect($res['tokens_subscribed'])->toBe(100);

        // Weakness: automatically marks KYC verified = true without verifying Party registry
        expect($res['holding']->is_kyc_verified)->toBeTrue();
    }

    public function test_distribute_dividend_action_status_salah(): void
    {
        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-DIV01',
            'name' => 'Dividend Asset',
            'underlying_asset_type' => 'mall_unit',
            'underlying_asset_id' => 'ML-003',
            'appraisal_value_idr' => 100_000_000,
            'total_supply_tokens' => 3,
            'token_price_idr' => 33_333_333,
            'status' => 'active',
        ]);

        // 3 holders each holding 1 token
        RwaHolding::create(['rwa_asset_id' => $asset->id, 'user_id' => $this->investor1->id, 'token_balance' => 1]);
        RwaHolding::create(['rwa_asset_id' => $asset->id, 'user_id' => $this->investor2->id, 'token_balance' => 1]);

        $action = app(DistributeProRataRwaRentalYieldAction::class);
        $res = $action->handle(
            rwaAssetId: $asset->id,
            totalRevenuePoolIdr: 10_000 // pool is manual input, not ledger-backed
        );

        // Weakness: Floor division leaves dust in rounding reserve (10000 / 3 = 3333 * 2 = 6666, dust = 3334)
        // Violates PR2 / U-1 Hamilton apportionment where Σ = total pool
        expect($res['rounding_reserve_idr'])->toBeGreaterThan(0);
        expect($res['distributed_idr'] + $res['rounding_reserve_idr'])->toBe(10_000);
    }

    public function test_match_orderbook_trade_status_sebagian(): void
    {
        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-TRD01',
            'name' => 'Trade Asset',
            'underlying_asset_type' => 'mall_unit',
            'underlying_asset_id' => 'ML-004',
            'appraisal_value_idr' => 100_000_000,
            'total_supply_tokens' => 1_000,
            'token_price_idr' => 100_000,
            'status' => 'active',
        ]);

        RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor2->id,
            'token_balance' => 50,
        ]);

        $buyOrder = RwaOrderbookOrder::create([
            'order_code' => 'BUY-001',
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor1->id,
            'side' => 'BUY',
            'quantity' => 20,
            'filled_quantity' => 0,
            'price_per_token_idr' => 100_000,
            'status' => 'open',
        ]);

        $sellOrder = RwaOrderbookOrder::create([
            'order_code' => 'SELL-001',
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor2->id,
            'side' => 'SELL',
            'quantity' => 20,
            'filled_quantity' => 0,
            'price_per_token_idr' => 100_000,
            'status' => 'open',
        ]);

        $action = app(MatchSecondaryRwaOrderbookTradeAction::class);
        $res = $action->handle($buyOrder->id, $sellOrder->id);

        expect($res['matched_qty'])->toBe(20);
        expect($res['trade_price_idr'])->toBe(100_000);

        // Weakness: Balances mutated directly in rwa_holdings without append-only transfer log
        $holding1 = RwaHolding::where('rwa_asset_id', $asset->id)->where('user_id', $this->investor1->id)->first();
        expect($holding1->token_balance)->toBe(20);
    }
}
