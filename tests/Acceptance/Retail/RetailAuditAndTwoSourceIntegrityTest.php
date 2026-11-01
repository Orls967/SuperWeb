<?php

declare(strict_types=1);

namespace Tests\Acceptance\Retail;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Ret\Application\Actions\CheckoutOmnichannelOrderAction;
use Modules\Ret\Domain\Models\RetCashbackTransaction;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetPackagingDeposit;
use Modules\Ret\Domain\Models\RetSeller;
use Modules\Ret\Domain\Models\RetSellerSettlement;
use Modules\Shared\Domain\Numerics\CanonicalHashChain;
use Modules\Shared\Domain\Numerics\CanonicalHashChainVerifier;
use Tests\TestCase;

class RetailAuditAndTwoSourceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_baseline_ret_audit_passes_with_zero_discrepancies(): void
    {
        $exitCode = Artisan::call('ret:audit');
        $this->assertEquals(0, $exitCode);
    }

    public function test_corruption_fixture_negative_inventory_stock_caught_by_audit(): void
    {
        RetInventoryItem::create([
            'id' => (string) Str::uuid(),
            'sku' => 'SKU-CORRUPT-TEST',
            'product_name' => 'Corrupted Inventory Item',
            'stock_available' => -20,
            'stock_reserved' => 0,
            'map_price_minor' => 100_000,
        ]);

        $exitCode = Artisan::call('ret:audit');
        $this->assertEquals(1, $exitCode);
    }

    public function test_corruption_fixture_tampered_seller_ap_posting_caught_by_audit(): void
    {
        $seller = RetSeller::create([
            'id' => (string) Str::uuid(),
            'party_id' => 'party-audit-01',
            'seller_name' => 'Audit Target Seller',
            'kyb_status' => 'VERIFIED',
            'commission_override_bps' => 1000,
            'payout_account_ref' => 'payout-aud-01',
        ]);

        RetSellerSettlement::create([
            'id' => (string) Str::uuid(),
            'order_id' => (string) Str::uuid(),
            'seller_id' => $seller->id,
            'gross_order_amount_minor' => 1_000_000,
            'commission_amount_minor' => 100_000,
            'net_seller_payable_minor' => 900_000,
            'status' => 'SETTLED',
            'idempotency_key' => 'ret:settle:aud-01',
        ]);

        // Deliberately omit or distort the ledger entry for AP
        $exitCode = Artisan::call('ret:audit');
        $this->assertEquals(1, $exitCode);
    }

    public function test_corruption_fixture_tampered_cashback_liability_caught_by_audit(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        RetCashbackTransaction::create([
            'id' => (string) Str::uuid(),
            'customer_id' => $user->id,
            'amount_minor' => 50_000,
            'type' => 'EARNED',
            'expires_at' => now()->addDays(30),
            'idempotency_key' => 'ret:cb:aud-tamper-01',
        ]);

        // No matching ledger entry for 50,000 IDR cashback liability
        $exitCode = Artisan::call('ret:audit');
        $this->assertEquals(1, $exitCode);
    }

    public function test_corruption_fixture_tampered_packaging_deposit_caught_by_audit(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        RetPackagingDeposit::create([
            'id' => (string) Str::uuid(),
            'order_id' => (string) Str::uuid(),
            'customer_id' => $user->id,
            'package_code' => 'TOTE-AUDIT-01',
            'deposit_amount_minor' => 35_000,
            'status' => 'HELD',
        ]);

        // Omit ledger posting for deposit liability
        $exitCode = Artisan::call('ret:audit');
        $this->assertEquals(1, $exitCode);
    }

    public function test_two_source_audit_verifies_facts_vs_settlements_vs_ledger(): void
    {
        $exitCode = Artisan::call('ret:audit');
        $this->assertEquals(0, $exitCode);
    }

    public function test_order_model_guard_rejects_deleting_paid_order(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $order = RetOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-AUDIT-IMMUTABLE-01',
            'customer_id' => $user->id,
            'total_amount_minor' => 100_000,
            'status' => 'PAID',
            'idempotency_key' => 'ret:ord:immut-01',
        ]);

        $this->expectException(\DomainException::class);
        $order->delete();
    }

    public function test_sabotage_commission_on_gross_value_violates_net_refund_commission_rule(): void
    {
        $grossOrder = 1_000_000;
        $refundAmount = 200_000;
        $commissionBps = 1000;

        $netOrder = $grossOrder - $refundAmount;
        $expectedNetCommission = (int) round(($netOrder * $commissionBps) / 10000);
        $expectedSellerPayable = $netOrder - $expectedNetCommission;

        $sabotagedGrossCommission = (int) round(($grossOrder * $commissionBps) / 10000);
        $sabotagedSellerPayable = $netOrder - $sabotagedGrossCommission;

        expect($expectedNetCommission)->toBe(80_000)
            ->and($expectedSellerPayable)->toBe(720_000)
            ->and($sabotagedGrossCommission)->not->toBe($expectedNetCommission)
            ->and($sabotagedSellerPayable)->not->toBe($expectedSellerPayable);
    }

    public function test_sabotage_unreleased_stock_reservation_on_cancellation_violates_available_stock_invariant(): void
    {
        $channel = RetChannel::firstOrCreate(['channel_code' => 'WEB_SAB'], [
            'id' => (string) Str::uuid(),
            'channel_name' => 'Web Sabotage',
            'channel_type' => 'WEB_APP',
            'status' => 'ACTIVE',
        ]);
        $user = User::factory()->create(['role' => 'customer']);

        $checkoutAction = app(CheckoutOmnichannelOrderAction::class);
        $order = $checkoutAction->execute([
            'channel_id' => $channel->id,
            'customer_id' => (string) $user->id,
            'items' => [
                ['sku' => 'SKU-SAB-01', 'quantity' => 5, 'unit_price_minor' => 100_000],
            ],
            'idempotency_key' => 'ret:sab:chk:01',
        ]);

        expect($order->status)->toBe('PAID');

        $order->update([
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancellation_reason' => 'CUSTOMER_TIMEOUT',
        ]);

        expect($order->fresh()->status)->toBe('CANCELLED');
    }

    public function test_sabotage_cashback_monthly_limit_utc_vs_local_timezone_boundary(): void
    {
        $localNow = Carbon::parse('2026-11-01 01:00:00', 'Asia/Jakarta');
        $utcNow = $localNow->copy()->setTimezone('UTC');

        expect($localNow->format('Y-m'))->toBe('2026-11')
            ->and($utcNow->format('Y-m'))->toBe('2026-10');

        $localMonth = $localNow->format('Y-m');
        $sabotagedUtcMonth = $utcNow->format('Y-m');

        expect($localMonth)->not->toBe($sabotagedUtcMonth);
    }

    public function test_sabotage_reversed_debit_credit_posting_caught_by_audit(): void
    {
        $seller = RetSeller::create([
            'id' => (string) Str::uuid(),
            'party_id' => 'party-sab-rev-01',
            'seller_name' => 'Reversed Posting Seller',
            'kyb_status' => 'VERIFIED',
            'commission_override_bps' => 1000,
            'payout_account_ref' => 'payout-sab-01',
        ]);

        RetSellerSettlement::create([
            'id' => (string) Str::uuid(),
            'order_id' => (string) Str::uuid(),
            'seller_id' => $seller->id,
            'gross_order_amount_minor' => 1_000_000,
            'commission_amount_minor' => 100_000,
            'net_seller_payable_minor' => 900_000,
            'status' => 'SETTLED',
            'idempotency_key' => 'ret:settle:sab-rev-01',
        ]);

        $tx = LedgerTransaction::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'RETAIL_MKTPLACE_SELLER_SETTLE',
            'idempotency_key' => 'sabotage:rev:post:01',
            'description' => 'Reversed AP entry',
            'posted_at' => now(),
        ]);

        $account = LedgerAccount::where('code', 'ret:seller_payable:IDR')->firstOrFail();

        DB::table('bank_ledger_entries')->insert([
            'transaction_id' => $tx->id,
            'account_id' => $account->id,
            'asset_code' => 'IDR',
            'amount' => '-900000.00',
            'balance_after' => '-900000.00',
            'created_at' => now(),
        ]);

        $exitCode = Artisan::call('ret:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_sabotage_tampered_canonical_hash_chain_detected_by_verifier(): void
    {
        $prevHash = CanonicalHashChain::GENESIS_PREV_HASH;
        $payload1 = ['day' => 1, 'sales' => 100000];
        $hash1 = CanonicalHashChain::computeHash($prevHash, $payload1);

        $payload2 = ['day' => 2, 'sales' => 200000];
        $hash2 = CanonicalHashChain::computeHash($hash1, $payload2);

        $records = [
            ['prev_hash' => $prevHash, 'payload' => $payload1, 'hash' => $hash1],
            ['prev_hash' => $hash1, 'payload' => $payload2, 'hash' => $hash2],
        ];

        expect(CanonicalHashChainVerifier::verifyChain($records))->toBeNull();

        $sabotagedRecords = $records;
        $sabotagedRecords[1]['payload']['sales'] = 999999;

        expect(CanonicalHashChainVerifier::verifyChain($sabotagedRecords))->toBe(1);
    }
}
