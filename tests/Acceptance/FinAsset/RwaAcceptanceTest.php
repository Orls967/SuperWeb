<?php

declare(strict_types=1);

namespace Tests\Acceptance\FinAsset;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Rwa\Application\Services\RwaDividendService;
use Modules\Rwa\Application\Services\RwaInvestorService;
use Modules\Rwa\Application\Services\RwaOfferingService;
use Modules\Rwa\Application\Services\RwaOrderbookService;
use Modules\Rwa\Application\Services\RwaTokenService;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Domain\Models\RwaInvestorVerification;
use Modules\Rwa\Domain\Models\RwaOffering;
use Modules\Rwa\Domain\Models\RwaOfferingOrder;
use Modules\Rwa\Domain\Models\RwaTokenTransfer;
use Modules\Rwa\Ledger\RwaAccounts;
use Tests\TestCase;

/**
 * Acceptance Tests: LINI 4 Keuangan Aset - RWA (§2F Slice 1).
 *
 * Menguji seluruh aturan bisnis R-1 s.d. R-5:
 * - Tokenisasi dengan four-eyes approval (approver != creator)
 * - Verifikasi investor KYC aktif (403 bila tidak aktif)
 * - Penawaran perdana (IPO) dengan hold, Hamilton allocation saat oversubscribed, capture & release
 * - Dividen dari pendapatan ledger-backed via Hamilton apportionment
 * - Perdagangan sekunder dengan transfer log append-only & settlement ledger kas
 * - Audit command rwa:audit dua-sumber & fixture korupsi
 */
class RwaAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $issuer;

    protected User $complianceOfficer;

    protected User $investor1;

    protected User $investor2;

    protected User $investor3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->issuer = User::factory()->create(['role' => 'asset_manager']);
        $this->complianceOfficer = User::factory()->create(['role' => 'admin']);
        $this->investor1 = User::factory()->create(['role' => 'investor']);
        $this->investor2 = User::factory()->create(['role' => 'investor']);
        $this->investor3 = User::factory()->create(['role' => 'investor']);

        $this->issuer->walletAccount('IDR');
        $this->investor1->walletAccount('IDR');
        $this->investor2->walletAccount('IDR');
        $this->investor3->walletAccount('IDR');
    }

    public function test_01_tokenization_requires_valid_idempotency_key(): void
    {
        /** @var RwaTokenService $service */
        $service = app(RwaTokenService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->createDraftAsset([
            'symbol' => 'RWA-PROP01',
            'name' => 'Mall Atrium Lot 101',
            'underlying_asset_type' => 'mall_unit',
            'underlying_asset_id' => 'UNT-101',
            'appraisal_value_idr' => 1_000_000_000,
            'total_supply_tokens' => 100_000,
        ], idempotencyKey: '');
    }

    public function test_02_tokenization_creates_draft_state_not_active(): void
    {
        $service = app(RwaTokenService::class);
        $asset = $service->createDraftAsset([
            'symbol' => 'RWA-PROP02',
            'name' => 'Logistics Hub Unit 2',
            'underlying_asset_type' => 'warehouse',
            'underlying_asset_id' => 'WH-002',
            'appraisal_value_idr' => 2_000_000_000,
            'total_supply_tokens' => 200_000,
            'creator_id' => $this->issuer->id,
        ], idempotencyKey: 'rwa:tok:draft:002');

        expect($asset)->toBeInstanceOf(RwaAsset::class)
            ->and($asset->status)->toBe('draft')
            ->and($asset->approved_at)->toBeNull();
    }

    public function test_03_four_eyes_approval_rejects_self_approval(): void
    {
        $service = app(RwaTokenService::class);
        $asset = $service->createDraftAsset([
            'symbol' => 'RWA-PROP03',
            'name' => 'Logistics Hub Unit 3',
            'underlying_asset_type' => 'warehouse',
            'underlying_asset_id' => 'WH-003',
            'appraisal_value_idr' => 1_500_000_000,
            'total_supply_tokens' => 150_000,
            'creator_id' => $this->issuer->id,
        ], idempotencyKey: 'rwa:tok:draft:003');

        $this->expectException(\DomainException::class);
        // Creator cannot approve their own asset
        $service->approveAsset($asset->id, approverId: $this->issuer->id, idempotencyKey: 'rwa:appr:003');
    }

    public function test_04_four_eyes_approval_succeeds_with_different_approver(): void
    {
        $service = app(RwaTokenService::class);
        $asset = $service->createDraftAsset([
            'symbol' => 'RWA-PROP04',
            'name' => 'Logistics Hub Unit 4',
            'underlying_asset_type' => 'warehouse',
            'underlying_asset_id' => 'WH-004',
            'appraisal_value_idr' => 1_000_000_000,
            'total_supply_tokens' => 100_000,
            'creator_id' => $this->issuer->id,
        ], idempotencyKey: 'rwa:tok:draft:004');

        $approved = $service->approveAsset($asset->id, approverId: $this->complianceOfficer->id, idempotencyKey: 'rwa:appr:004');
        expect($approved->status)->toBe('approved')
            ->and($approved->approved_by_primary_id)->toBe($this->complianceOfficer->id)
            ->and($approved->approved_at)->not->toBeNull();
    }

    public function test_05_asset_issuance_calculates_integer_token_price(): void
    {
        $service = app(RwaTokenService::class);
        $asset = $service->createDraftAsset([
            'symbol' => 'RWA-PROP05',
            'name' => 'Logistics Hub Unit 5',
            'underlying_asset_type' => 'warehouse',
            'underlying_asset_id' => 'WH-005',
            'appraisal_value_idr' => 1_000_000_000,
            'total_supply_tokens' => 33_333,
            'creator_id' => $this->issuer->id,
        ], idempotencyKey: 'rwa:tok:draft:005');

        $service->approveAsset($asset->id, approverId: $this->complianceOfficer->id, idempotencyKey: 'rwa:appr:005');
        $issued = $service->issueAsset($asset->id, idempotencyKey: 'rwa:iss:005');

        expect($issued->status)->toBe('issued')
            ->and($issued->token_price_idr)->toBe(intdiv(1_000_000_000, 33_333));
    }

    public function test_06_unverified_investor_is_rejected_with_403_when_subscribing(): void
    {
        $offeringService = app(RwaOfferingService::class);

        $this->expectException(AuthorizationException::class);
        // investor1 does not have active KYC verification
        $offeringService->placeOrder(
            offeringId: 1,
            investorId: $this->investor1->id,
            quantity: 50,
            idempotencyKey: 'rwa:ord:fail:001'
        );
    }

    public function test_07_investor_kyc_verification_registry_records_validity_period(): void
    {
        $investorService = app(RwaInvestorService::class);
        $verification = $investorService->verifyInvestor(
            userId: $this->investor1->id,
            kycDocumentId: 'KYC-DOC-101',
            approvalId: 'APPR-KYC-001',
            validUntil: now()->addYear(),
            idempotencyKey: 'rwa:kyc:verif:001'
        );

        expect($verification)->toBeInstanceOf(RwaInvestorVerification::class)
            ->and($verification->user_id)->toBe($this->investor1->id)
            ->and($verification->status)->toBe('VERIFIED')
            ->and($verification->expires_at)->toBeGreaterThan(now());
    }

    public function test_08_expired_kyc_verification_rejects_investor_action(): void
    {
        $investorService = app(RwaInvestorService::class);
        $investorService->verifyInvestor(
            userId: $this->investor1->id,
            kycDocumentId: 'KYC-DOC-EXP',
            approvalId: 'APPR-KYC-EXP',
            validUntil: now()->subDay(), // expired
            idempotencyKey: 'rwa:kyc:verif:exp'
        );

        expect($investorService->isVerified($this->investor1->id))->toBeFalse();
    }

    public function test_09_primary_offering_creation_validates_issued_asset(): void
    {
        $tokenService = app(RwaTokenService::class);
        $asset = $tokenService->createDraftAsset([
            'symbol' => 'RWA-OFF09',
            'name' => 'Asset for Offering 09',
            'underlying_asset_type' => 'mall_unit',
            'underlying_asset_id' => 'UNT-09',
            'appraisal_value_idr' => 500_000_000,
            'total_supply_tokens' => 50_000,
            'creator_id' => $this->issuer->id,
        ], idempotencyKey: 'rwa:tok:draft:009');

        $offeringService = app(RwaOfferingService::class);

        // Draft asset cannot be offered
        $this->expectException(\DomainException::class);
        $offeringService->createOffering([
            'rwa_asset_id' => $asset->id,
            'offering_code' => 'IPO-009',
            'tokens_offered' => 20_000,
            'price_per_token_idr' => 10_000,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(5),
        ], idempotencyKey: 'rwa:off:create:009');
    }

    public function test_10_primary_offering_places_hold_on_investor_funds(): void
    {
        $offering = $this->createActiveOfferingWithApprovedAsset('RWA-OFF10', 1000, 10_000);
        $this->verifyInvestor($this->investor1);

        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '10000000'])->save();

        $offeringService = app(RwaOfferingService::class);
        $order = $offeringService->placeOrder(
            offeringId: $offering->id,
            investorId: $this->investor1->id,
            quantity: 100, // 100 * 10,000 = 1,000,000 IDR
            idempotencyKey: 'rwa:ord:hold:010'
        );

        expect($order)->toBeInstanceOf(RwaOfferingOrder::class)
            ->and($order->status)->toBe('HELD')
            ->and($order->amount_held_idr)->toBe(1_000_000);
    }

    public function test_11_offering_order_fails_if_investor_has_insufficient_cash(): void
    {
        $offering = $this->createActiveOfferingWithApprovedAsset('RWA-OFF11', 1000, 10_000);
        $this->verifyInvestor($this->investor1);

        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '500000'])->save();

        $offeringService = app(RwaOfferingService::class);

        $this->expectException(\DomainException::class);
        $offeringService->placeOrder(
            offeringId: $offering->id,
            investorId: $this->investor1->id,
            quantity: 100, // Requires 1,000,000 IDR, user has only 500,000
            idempotencyKey: 'rwa:ord:fail:011'
        );
    }

    public function test_12_offering_allocation_pro_rata_hamilton_when_oversubscribed(): void
    {
        // Supply = 1,000 tokens. Total orders: Investor1 = 600, Investor2 = 300, Investor3 = 300 (Total demand = 1,200)
        // Vector from B0′.1: allocations = [500, 250, 250]
        $offering = $this->createActiveOfferingWithApprovedAsset('RWA-OFF12', 1000, 10_000);
        $this->verifyInvestor($this->investor1);
        $this->verifyInvestor($this->investor2);
        $this->verifyInvestor($this->investor3);

        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '10000000'])->save();
        $this->investor2->walletAccount('IDR')->forceFill(['cached_balance' => '10000000'])->save();
        $this->investor3->walletAccount('IDR')->forceFill(['cached_balance' => '10000000'])->save();

        $offeringService = app(RwaOfferingService::class);
        $offeringService->placeOrder($offering->id, $this->investor1->id, 600, 'rwa:ord:sub:012:1');
        $offeringService->placeOrder($offering->id, $this->investor2->id, 300, 'rwa:ord:sub:012:2');
        $offeringService->placeOrder($offering->id, $this->investor3->id, 300, 'rwa:ord:sub:012:3');

        $result = $offeringService->closeAndAllocate($offering->id, idempotencyKey: 'rwa:off:close:012');

        expect($result['allocations'][$this->investor1->id])->toBe(500)
            ->and($result['allocations'][$this->investor2->id])->toBe(250)
            ->and($result['allocations'][$this->investor3->id])->toBe(250)
            ->and(array_sum($result['allocations']))->toBe(1000);
    }

    public function test_13_offering_settlement_captures_allocated_and_releases_excess(): void
    {
        $offering = $this->createActiveOfferingWithApprovedAsset('RWA-OFF13', 1000, 10_000);
        $this->verifyInvestor($this->investor1);
        $this->verifyInvestor($this->investor2);

        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '10000000'])->save();
        $this->investor2->walletAccount('IDR')->forceFill(['cached_balance' => '10000000'])->save();

        $offeringService = app(RwaOfferingService::class);
        $order1 = $offeringService->placeOrder($offering->id, $this->investor1->id, 800, 'rwa:ord:sub:013:1');
        $order2 = $offeringService->placeOrder($offering->id, $this->investor2->id, 400, 'rwa:ord:sub:013:2');

        $offeringService->closeAndAllocate($offering->id, 'rwa:off:close:013');
        $offeringService->settleOffering($offering->id, 'rwa:off:settle:013');

        $order1->refresh();
        $order2->refresh();

        // Total demand = 1200, supply = 1000 -> 800:400 gives 667 and 333
        expect($order1->status)->toBe('SETTLED')
            ->and($order1->amount_captured_idr)->toBe(667 * 10_000)
            ->and($order1->amount_held_idr - $order1->amount_captured_idr)->toBe((800 - 667) * 10_000);
    }

    public function test_14_offering_settlement_writes_append_only_token_transfer_log(): void
    {
        $offering = $this->createActiveOfferingWithApprovedAsset('RWA-OFF14', 500, 10_000);
        $this->verifyInvestor($this->investor1);
        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '10000000'])->save();

        $offeringService = app(RwaOfferingService::class);
        $offeringService->placeOrder($offering->id, $this->investor1->id, 500, 'rwa:ord:sub:014');
        $offeringService->closeAndAllocate($offering->id, 'rwa:off:close:014');
        $offeringService->settleOffering($offering->id, 'rwa:off:settle:014');

        $transfers = RwaTokenTransfer::where('rwa_asset_id', $offering->rwa_asset_id)
            ->where('to_user_id', $this->investor1->id)
            ->get();

        expect($transfers)->toHaveCount(1)
            ->and($transfers->first()->transfer_type)->toBe('PRIMARY_ISSUANCE')
            ->and($transfers->first()->quantity)->toBe(500);
    }

    public function test_15_investor_token_balance_derived_from_transfer_log_and_holding(): void
    {
        $offering = $this->createActiveOfferingWithApprovedAsset('RWA-OFF15', 500, 10_000);
        $this->verifyInvestor($this->investor1);
        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '10000000'])->save();

        $offeringService = app(RwaOfferingService::class);
        $offeringService->placeOrder($offering->id, $this->investor1->id, 500, 'rwa:ord:sub:015');
        $offeringService->closeAndAllocate($offering->id, 'rwa:off:close:015');
        $offeringService->settleOffering($offering->id, 'rwa:off:settle:015');

        $holding = RwaHolding::where('rwa_asset_id', $offering->rwa_asset_id)
            ->where('user_id', $this->investor1->id)
            ->first();

        $sumTransfers = RwaTokenTransfer::where('rwa_asset_id', $offering->rwa_asset_id)
            ->where('to_user_id', $this->investor1->id)
            ->sum('quantity');

        expect($holding->token_balance)->toBe($sumTransfers)
            ->and($holding->token_balance)->toBe(500);
    }

    public function test_16_dividend_declaration_fails_if_pool_not_posted_in_ledger(): void
    {
        $dividendService = app(RwaDividendService::class);

        $this->expectException(\DomainException::class);
        // Revenue pool has 0 balance in ledger
        $dividendService->declareDividend(
            assetId: 1,
            period: '2026-Q1',
            declaredAmountIdr: 50_000_000,
            idempotencyKey: 'rwa:div:decl:fail:016'
        );
    }

    public function test_17_dividend_declaration_locks_ledger_pool_balance(): void
    {
        $asset = $this->createIssuedAssetWithHolders('RWA-DIV17');
        $pool = LedgerAccount::where('code', "rwa:pool:{$asset->token_symbol}:IDR")->first();
        $pool->forceFill(['cached_balance' => '100000000'])->save();

        $dividendService = app(RwaDividendService::class);
        $declaration = $dividendService->declareDividend(
            assetId: $asset->id,
            period: '2026-Q1',
            declaredAmountIdr: 50_000_000,
            idempotencyKey: 'rwa:div:decl:017'
        );

        expect($declaration->total_revenue_pool_idr)->toBe(50_000_000)
            ->and($declaration->status)->toBe('DECLARED');
    }

    public function test_18_dividend_payout_uses_hamilton_apportionment_zero_dust(): void
    {
        // 3 holders with weights 333, 333, 334 on 10,000,000 total pool (Vector 2 from B0′.1)
        // Expected payout: 3,330,000, 3,330,000, 3,340,000. Sum = 10,000,000, dust = 0
        $asset = $this->createIssuedAssetWithSpecificHolders('RWA-DIV18', [
            $this->investor1->id => 333,
            $this->investor2->id => 333,
            $this->investor3->id => 334,
        ]);

        $pool = LedgerAccount::where('code', "rwa:pool:{$asset->token_symbol}:IDR")->first();
        $pool->forceFill(['cached_balance' => '10000000'])->save();

        $dividendService = app(RwaDividendService::class);
        $dividendService->declareDividend($asset->id, '2026-M01', 10_000_000, 'rwa:div:decl:018');
        $payouts = $dividendService->distributeDividend($asset->id, '2026-M01', 'rwa:div:pay:018');

        expect($payouts[$this->investor1->id])->toBe(3_330_000)
            ->and($payouts[$this->investor2->id])->toBe(3_330_000)
            ->and($payouts[$this->investor3->id])->toBe(3_340_000)
            ->and(array_sum($payouts))->toBe(10_000_000);
    }

    public function test_19_dividend_payout_posts_credits_to_investor_cash_wallets(): void
    {
        $asset = $this->createIssuedAssetWithSpecificHolders('RWA-DIV19', [
            $this->investor1->id => 500,
            $this->investor2->id => 500,
        ]);

        $pool = LedgerAccount::where('code', "rwa:pool:{$asset->token_symbol}:IDR")->first();
        $pool->forceFill(['cached_balance' => '20000000'])->save();

        $initialBal1 = (int) $this->investor1->walletAccount('IDR')->cached_balance;

        $dividendService = app(RwaDividendService::class);
        $dividendService->declareDividend($asset->id, '2026-M02', 20_000_000, 'rwa:div:decl:019');
        $dividendService->distributeDividend($asset->id, '2026-M02', 'rwa:div:pay:019');

        $this->investor1->walletAccount('IDR')->refresh();
        expect((int) $this->investor1->walletAccount('IDR')->cached_balance)->toBe($initialBal1 + 10_000_000);
    }

    public function test_20_secondary_orderbook_places_buy_limit_order(): void
    {
        $asset = $this->createIssuedAssetWithHolders('RWA-SEC20');
        $this->verifyInvestor($this->investor1);
        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '5000000'])->save();

        $orderbookService = app(RwaOrderbookService::class);
        $order = $orderbookService->placeLimitOrder([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor1->id,
            'side' => 'BUY',
            'quantity' => 20,
            'price_per_token_idr' => 100_000,
        ], idempotencyKey: 'rwa:ob:buy:020');

        expect($order->status)->toBe('OPEN')
            ->and($order->side)->toBe('BUY');
    }

    public function test_21_secondary_orderbook_places_sell_limit_order_checking_unlocked_tokens(): void
    {
        $asset = $this->createIssuedAssetWithHolders('RWA-SEC21');
        $this->verifyInvestor($this->investor1);

        $orderbookService = app(RwaOrderbookService::class);

        $this->expectException(\DomainException::class);
        // investor2 has no tokens
        $orderbookService->placeLimitOrder([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor2->id,
            'side' => 'SELL',
            'quantity' => 50,
            'price_per_token_idr' => 100_000,
        ], idempotencyKey: 'rwa:ob:sell:fail:021');
    }

    public function test_22_secondary_orderbook_matches_compatible_orders(): void
    {
        $asset = $this->createIssuedAssetWithSpecificHolders('RWA-SEC22', [
            $this->investor2->id => 100, // Seller
        ]);
        $this->verifyInvestor($this->investor1);
        $this->verifyInvestor($this->investor2);

        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '10000000'])->save();

        $orderbookService = app(RwaOrderbookService::class);
        $buy = $orderbookService->placeLimitOrder([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor1->id,
            'side' => 'BUY',
            'quantity' => 40,
            'price_per_token_idr' => 100_000,
        ], 'rwa:ob:buy:022');

        $sell = $orderbookService->placeLimitOrder([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor2->id,
            'side' => 'SELL',
            'quantity' => 40,
            'price_per_token_idr' => 100_000,
        ], 'rwa:ob:sell:022');

        $trade = $orderbookService->matchOrders($buy->id, $sell->id, idempotencyKey: 'rwa:trade:match:022');

        expect($trade['matched_quantity'])->toBe(40)
            ->and($trade['trade_price_idr'])->toBe(100_000)
            ->and($trade['status'])->toBe('SETTLED');
    }

    public function test_23_secondary_trade_settles_cash_and_deducts_platform_fee(): void
    {
        $asset = $this->createIssuedAssetWithSpecificHolders('RWA-SEC23', [
            $this->investor2->id => 50,
        ]);
        $this->verifyInvestor($this->investor1);
        $this->verifyInvestor($this->investor2);

        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '5000000'])->save();
        $this->investor2->walletAccount('IDR')->forceFill(['cached_balance' => '0'])->save();

        $orderbookService = app(RwaOrderbookService::class);
        $buy = $orderbookService->placeLimitOrder([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor1->id,
            'side' => 'BUY',
            'quantity' => 10,
            'price_per_token_idr' => 100_000, // Gross 1,000,000 IDR
        ], 'rwa:ob:buy:023');

        $sell = $orderbookService->placeLimitOrder([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor2->id,
            'side' => 'SELL',
            'quantity' => 10,
            'price_per_token_idr' => 100_000,
        ], 'rwa:ob:sell:023');

        $orderbookService->matchOrders($buy->id, $sell->id, 'rwa:trade:match:023');

        $this->investor1->walletAccount('IDR')->refresh();
        $this->investor2->walletAccount('IDR')->refresh();

        // 1,000,000 IDR transferred from buyer to seller; 25 bps (2,500 IDR) platform fee deducted from seller
        expect((int) $this->investor1->walletAccount('IDR')->cached_balance)->toBe(4_000_000)
            ->and((int) $this->investor2->walletAccount('IDR')->cached_balance)->toBe(997_500);
    }

    public function test_24_secondary_trade_creates_token_transfer_log_facts(): void
    {
        $asset = $this->createIssuedAssetWithSpecificHolders('RWA-SEC24', [
            $this->investor2->id => 50,
        ]);
        $this->verifyInvestor($this->investor1);
        $this->verifyInvestor($this->investor2);
        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '5000000'])->save();

        $orderbookService = app(RwaOrderbookService::class);
        $buy = $orderbookService->placeLimitOrder([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor1->id,
            'side' => 'BUY',
            'quantity' => 15,
            'price_per_token_idr' => 100_000,
        ], 'rwa:ob:buy:024');

        $sell = $orderbookService->placeLimitOrder([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor2->id,
            'side' => 'SELL',
            'quantity' => 15,
            'price_per_token_idr' => 100_000,
        ], 'rwa:ob:sell:024');

        $orderbookService->matchOrders($buy->id, $sell->id, 'rwa:trade:match:024');

        $transfer = RwaTokenTransfer::where('rwa_asset_id', $asset->id)
            ->where('from_user_id', $this->investor2->id)
            ->where('to_user_id', $this->investor1->id)
            ->first();

        expect($transfer)->not->toBeNull()
            ->and($transfer->quantity)->toBe(15)
            ->and($transfer->transfer_type)->toBe('SECONDARY_TRADE');
    }

    public function test_25_token_transfers_table_is_append_only(): void
    {
        $transfer = RwaTokenTransfer::create([
            'rwa_asset_id' => 1,
            'from_user_id' => $this->investor2->id,
            'to_user_id' => $this->investor1->id,
            'quantity' => 10,
            'price_per_token_idr' => 100_000,
            'transfer_type' => 'SECONDARY_TRADE',
            'idempotency_key' => 'rwa:raw:fact:025',
        ]);

        $this->expectException(\DomainException::class);
        // Model rejects updates on facts per PR5
        $transfer->update(['quantity' => 20]);
    }

    public function test_26_idempotency_same_key_same_payload_returns_cached_result(): void
    {
        $investorService = app(RwaInvestorService::class);
        $key = 'rwa:kyc:idem:026';

        $res1 = $investorService->verifyInvestor(
            userId: $this->investor1->id,
            kycDocumentId: 'DOC-026',
            approvalId: 'APPR-026',
            validUntil: now()->addMonths(6),
            idempotencyKey: $key
        );

        $res2 = $investorService->verifyInvestor(
            userId: $this->investor1->id,
            kycDocumentId: 'DOC-026',
            approvalId: 'APPR-026',
            validUntil: now()->addMonths(6),
            idempotencyKey: $key
        );

        expect($res1->id)->toBe($res2->id);
    }

    public function test_27_idempotency_same_key_different_payload_throws_conflict_exception(): void
    {
        $investorService = app(RwaInvestorService::class);
        $key = 'rwa:kyc:idem:027';

        $investorService->verifyInvestor(
            userId: $this->investor1->id,
            kycDocumentId: 'DOC-027-A',
            approvalId: 'APPR-027',
            validUntil: now()->addMonths(6),
            idempotencyKey: $key
        );

        $this->expectException(\DomainException::class);
        // Different KYC document ID under same key
        $investorService->verifyInvestor(
            userId: $this->investor1->id,
            kycDocumentId: 'DOC-027-B',
            approvalId: 'APPR-027',
            validUntil: now()->addMonths(6),
            idempotencyKey: $key
        );
    }

    public function test_28_ledger_negative_balance_guard_rejects_overdrawn_payout(): void
    {
        $asset = $this->createIssuedAssetWithHolders('RWA-NEG28');
        $pool = LedgerAccount::where('code', "rwa:pool:{$asset->token_symbol}:IDR")->first();
        $pool->forceFill(['cached_balance' => '1000000'])->save();

        $dividendService = app(RwaDividendService::class);

        $this->expectException(\DomainException::class);
        // Attempting to distribute 5,000,000 with only 1,000,000 in pool
        $dividendService->declareDividend($asset->id, '2026-M03', 5_000_000, 'rwa:div:neg:028');
    }

    public function test_29_http_offerings_index_requires_authentication(): void
    {
        $response = $this->get('/rwa/offerings');
        $response->assertRedirect('/login');
    }

    public function test_30_http_offerings_index_renders_for_authorized_investor(): void
    {
        $response = $this->actingAs($this->investor1)->get('/rwa/offerings');
        $response->assertOk();
    }

    public function test_31_http_subscribe_offering_requires_idempotency_key_header(): void
    {
        $response = $this->actingAs($this->investor1)->postJson('/rwa/offerings/1/subscribe', [
            'quantity' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_32_http_orderbook_view_displays_active_orders(): void
    {
        $response = $this->actingAs($this->investor1)->get('/rwa/orderbook');
        $response->assertOk();
    }

    public function test_33_http_dividends_view_displays_declared_distributions(): void
    {
        $response = $this->actingAs($this->investor1)->get('/rwa/yield-distribution');
        $response->assertOk();
    }

    public function test_34_audit_two_source_passes_with_reconciled_balances(): void
    {
        $exitCode = Artisan::call('rwa:audit');
        expect($exitCode)->toBe(0);
    }

    public function test_35_audit_two_source_detects_corrupted_token_balance(): void
    {
        $asset = $this->createIssuedAssetWithSpecificHolders('RWA-CORR35', [
            $this->investor1->id => 100,
        ]);

        // Maliciously corrupt holding without transfer log
        RwaHolding::where('rwa_asset_id', $asset->id)
            ->where('user_id', $this->investor1->id)
            ->update(['token_balance' => 999]);

        $exitCode = Artisan::call('rwa:audit');
        expect($exitCode)->not->toBe(0);
    }

    public function test_36_paused_asset_rejects_new_secondary_market_orders(): void
    {
        $asset = $this->createIssuedAssetWithHolders('RWA-PAUSE36');
        $asset->update(['status' => 'paused']);

        $orderbookService = app(RwaOrderbookService::class);

        $this->expectException(\DomainException::class);
        $orderbookService->placeLimitOrder([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->investor1->id,
            'side' => 'BUY',
            'quantity' => 10,
            'price_per_token_idr' => 100_000,
        ], 'rwa:ob:buy:pause:036');
    }

    // Helper methods for test fixtures
    protected function verifyInvestor(User $user): void
    {
        RwaInvestorVerification::updateOrCreate(
            ['user_id' => $user->id],
            [
                'kyc_document_ref' => "KYC-DOC-{$user->id}",
                'approval_ref' => "APPR-{$user->id}",
                'verified_at' => now(),
                'expires_at' => now()->addYear(),
                'status' => 'VERIFIED',
            ]
        );
    }

    protected function createActiveOfferingWithApprovedAsset(string $symbol, int $supply, int $price): RwaOffering
    {
        $asset = RwaAsset::create([
            'token_symbol' => $symbol,
            'name' => "Asset {$symbol}",
            'underlying_asset_type' => 'mall_unit',
            'underlying_asset_id' => "UNT-{$symbol}",
            'appraisal_value_idr' => $supply * $price,
            'total_supply_tokens' => $supply,
            'token_price_idr' => $price,
            'status' => 'issued',
            'approved_by_primary_id' => $this->complianceOfficer->id,
            'approved_at' => now(),
        ]);

        return RwaOffering::create([
            'rwa_asset_id' => $asset->id,
            'offering_code' => "IPO-{$symbol}",
            'tokens_offered' => $supply,
            'tokens_sold' => 0,
            'price_per_token_idr' => $price,
            'min_lot_size' => 1,
            'lockup_days' => 0,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(5),
            'status' => 'open',
        ]);
    }

    protected function createIssuedAssetWithHolders(string $symbol): RwaAsset
    {
        return $this->createIssuedAssetWithSpecificHolders($symbol, [
            $this->investor1->id => 100,
        ]);
    }

    /**
     * @param  array<int, int>  $holders  userId => tokens
     */
    protected function createIssuedAssetWithSpecificHolders(string $symbol, array $holders): RwaAsset
    {
        $totalTokens = array_sum($holders);
        $asset = RwaAsset::create([
            'token_symbol' => $symbol,
            'name' => "Asset {$symbol}",
            'underlying_asset_type' => 'mall_unit',
            'underlying_asset_id' => "UNT-{$symbol}",
            'appraisal_value_idr' => $totalTokens * 100_000,
            'total_supply_tokens' => $totalTokens,
            'token_price_idr' => 100_000,
            'status' => 'issued',
            'approved_by_primary_id' => $this->complianceOfficer->id,
            'approved_at' => now(),
        ]);

        RwaAccounts::revenuePool($symbol);

        foreach ($holders as $userId => $qty) {
            RwaHolding::create([
                'rwa_asset_id' => $asset->id,
                'user_id' => $userId,
                'token_balance' => $qty,
                'is_kyc_verified' => true,
            ]);

            RwaTokenTransfer::create([
                'rwa_asset_id' => $asset->id,
                'from_user_id' => null,
                'to_user_id' => $userId,
                'quantity' => $qty,
                'price_per_token_idr' => 100_000,
                'transfer_type' => 'PRIMARY_ISSUANCE',
                'idempotency_key' => "rwa:iss:init:{$symbol}:{$userId}",
            ]);
        }

        return $asset;
    }
}
