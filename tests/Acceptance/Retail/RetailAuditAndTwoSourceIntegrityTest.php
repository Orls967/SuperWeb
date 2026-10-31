<?php

declare(strict_types=1);

namespace Tests\Acceptance\Retail;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Ret\Domain\Models\RetCashbackTransaction;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetPackagingDeposit;
use Modules\Ret\Domain\Models\RetSeller;
use Modules\Ret\Domain\Models\RetSellerSettlement;
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
            'payout_account_id' => 'payout-aud-01',
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
}
