<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Mall\Application\Queries\AuditBillingQuery;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\InvoiceLine;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Ledger\MallAccounts;
use Tests\TestCase;

class AuditBillingQueryTest extends TestCase
{
    use RefreshDatabase;

    private User $tenantUser;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(MallSeeder::class);

        $this->tenantUser = User::firstOrCreate(
            ['email' => 'tenant@audit.com'],
            ['name' => 'Audit Tenant', 'role' => 'tenant', 'password' => bcrypt('password')]
        );
        $this->lease = Lease::first();
    }

    public function test_audit_passes_on_empty_and_consistent_state(): void
    {
        // Clear seeded invoices for a clean check
        InvoiceLine::query()->delete();
        Invoice::query()->delete();

        $query = new AuditBillingQuery;
        $result = $query->execute();

        $this->assertTrue($result['passed']);
        $this->assertSame(0, $result['invoices_checked']);
        $this->assertSame(0, $result['total_billed']);
        $this->assertSame(0, $result['total_paid']);
        $this->assertSame(0, $result['ledger_paid']);
        $this->assertEmpty($result['discrepancies']);
    }

    public function test_audit_passes_with_fully_paid_invoice_and_matching_ledger(): void
    {
        InvoiceLine::query()->delete();
        Invoice::query()->delete();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-AUDIT-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->lease->tenant_id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2026-10',
            'subtotal' => 10000000,
            'penalty_amount' => 500000,
            'total_amount' => 10500000,
            'paid_amount' => 10500000,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDays(7),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa Bulan Ini',
            'quantity' => 1,
            'unit_price' => 10000000,
            'amount' => 10000000,
            'paid_amount' => 10000000,
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::PENALTY,
            'description' => 'Denda Keterlambatan',
            'quantity' => 1,
            'unit_price' => 500000,
            'amount' => 500000,
            'paid_amount' => 500000,
        ]);

        $revAcc = LedgerAccount::firstOrCreate(
            ['code' => 'revenue:mall:rent'],
            ['name' => 'Mall Rental Revenue', 'kind' => 'REVENUE', 'asset_code' => 'IDR', 'cached_balance' => '10500000']
        );
        $revAcc->update(['cached_balance' => '10500000']);

        $bankAcc = LedgerAccount::firstOrCreate(
            ['code' => 'asset:bank:main'],
            ['name' => 'Main Bank', 'kind' => 'ASSET', 'asset_code' => 'IDR', 'cached_balance' => '-10500000']
        );
        $bankAcc->update(['cached_balance' => '-10500000']);

        $tx = LedgerTransaction::create([
            'transaction_number' => 'TX-AUDIT-001',
            'idempotency_key' => 'idem-audit-001',
            'type' => TransactionType::PAYMENT->value,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'amount' => 10500000,
            'asset_code' => 'IDR',
            'description' => 'Payment for INV-AUDIT-001',
            'posted_at' => now(),
        ]);

        LedgerEntry::create([
            'transaction_id' => $tx->id,
            'account_id' => $revAcc->id,
            'amount' => 10500000,
            'balance_after' => 10500000,
            'entry_type' => 'CREDIT',
            'asset_code' => 'IDR',
        ]);
        LedgerEntry::create([
            'transaction_id' => $tx->id,
            'account_id' => $bankAcc->id,
            'amount' => -10500000,
            'balance_after' => -10500000,
            'entry_type' => 'DEBIT',
            'asset_code' => 'IDR',
        ]);

        $query = new AuditBillingQuery;
        $result = $query->execute();

        $this->assertTrue($result['passed']);
        $this->assertSame(1, $result['invoices_checked']);
        $this->assertSame(10500000, $result['total_billed']);
        $this->assertSame(10500000, $result['total_paid']);
        $this->assertSame(10500000, $result['ledger_paid']);
        $this->assertEmpty($result['discrepancies']);
    }

    public function test_audit_detects_all_invoice_discrepancies(): void
    {
        InvoiceLine::query()->delete();
        Invoice::query()->delete();

        // 1. Subtotal mismatch
        // 2. Penalty mismatch
        // 3. Total amount mismatch
        // 4. Paid amount mismatch
        // 5. PAID status with paid < total
        $invoice = Invoice::create([
            'invoice_number' => 'INV-AUDIT-ERR',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->lease->tenant_id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2026-10',
            'subtotal' => 9000000, // Should be 10000000
            'penalty_amount' => 200000, // Should be 500000
            'total_amount' => 9200000, // Should be 10500000
            'paid_amount' => 5000000, // Line paid is 4000000
            'status' => InvoiceStatus::PAID, // Paid < total
            'due_date' => now()->addDays(7),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa',
            'quantity' => 1,
            'unit_price' => 10000000,
            'amount' => 10000000,
            'paid_amount' => 4000000,
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::PENALTY,
            'description' => 'Denda',
            'quantity' => 1,
            'unit_price' => 500000,
            'amount' => 500000,
            'paid_amount' => 0,
        ]);

        // Revenue account negative
        LedgerAccount::create([
            'code' => 'revenue:mall:test_negative',
            'name' => 'Negative Rev',
            'kind' => 'REVENUE',
            'asset_code' => 'IDR',
            'cached_balance' => '-1000',
        ]);

        // Unbalanced global IDR
        $tx = LedgerTransaction::create([
            'transaction_number' => 'TX-AUDIT-UNBALANCED',
            'idempotency_key' => 'idem-audit-unbalanced',
            'type' => TransactionType::PAYMENT->value,
            'reference_type' => 'manual',
            'reference_id' => 1,
            'description' => 'Unbalanced tx',
            'amount' => 1000,
            'asset_code' => 'IDR',
            'posted_at' => now(),
        ]);
        $acc = LedgerAccount::create([
            'code' => 'expense:mall:misc',
            'name' => 'Mall Misc Expense',
            'kind' => 'EXPENSE',
            'asset_code' => 'IDR',
            'cached_balance' => '1000',
        ]);
        LedgerEntry::create([
            'transaction_id' => $tx->id,
            'account_id' => $acc->id,
            'amount' => 1000,
            'balance_after' => 1000,
            'entry_type' => 'DEBIT',
            'asset_code' => 'IDR',
        ]);

        $query = new AuditBillingQuery;
        $result = $query->execute();

        $this->assertFalse($result['passed']);
        $this->assertSame(1, $result['invoices_checked']);
        $this->assertSame(9200000, $result['total_billed']);
        $this->assertSame(5000000, $result['total_paid']);
        $this->assertSame(0, $result['ledger_paid']);

        $discrepancyText = implode("\n", $result['discrepancies']);
        $this->assertStringContainsString('Subtotal mismatch', $discrepancyText);
        $this->assertStringContainsString('Penalty mismatch', $discrepancyText);
        $this->assertStringContainsString('Total amount mismatch', $discrepancyText);
        $this->assertStringContainsString('Paid amount mismatch', $discrepancyText);
        $this->assertStringContainsString('Status is PAID but paid_amount', $discrepancyText);
        $this->assertStringContainsString('Ledger discrepancy!', $discrepancyText);
        $this->assertStringContainsString('has negative balance', $discrepancyText);
        $this->assertStringContainsString('Global IDR ledger balance is unbalanced', $discrepancyText);
    }

    public function test_audit_loyalty_model_c_pts_passes_when_synchronized(): void
    {
        InvoiceLine::query()->delete();
        Invoice::query()->delete();

        $user = User::factory()->create();

        PointBatch::create([
            'user_id' => $user->id,
            'points_earned' => 300,
            'points_remaining' => 300,
            'earned_at' => now(),
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        // User wallet balance 300
        LedgerAccount::create([
            'code' => "points:user:{$user->id}:PTS",
            'name' => "User {$user->id} PTS Wallet",
            'kind' => 'LIABILITY',
            'asset_code' => 'PTS',
            'cached_balance' => '300',
        ]);

        // Expense issued: -500, Revenue redeemed: 100, Revenue breakage: 100
        // Expected wallet sum: -(-500) - 100 - 100 = 300
        LedgerAccount::updateOrCreate(
            ['code' => MallAccounts::EXPENSE_POINTS_ISSUED_PTS],
            ['name' => 'Points Issued Expense', 'kind' => 'EXPENSE', 'asset_code' => 'PTS', 'cached_balance' => '-500']
        );
        LedgerAccount::updateOrCreate(
            ['code' => MallAccounts::REVENUE_POINTS_REDEEMED_PTS],
            ['name' => 'Points Redeemed Revenue', 'kind' => 'REVENUE', 'asset_code' => 'PTS', 'cached_balance' => '100']
        );
        LedgerAccount::updateOrCreate(
            ['code' => MallAccounts::REVENUE_POINTS_BREAKAGE_PTS],
            ['name' => 'Points Breakage Revenue', 'kind' => 'REVENUE', 'asset_code' => 'PTS', 'cached_balance' => '100']
        );

        $query = new AuditBillingQuery;
        $result = $query->execute();

        $this->assertTrue($result['passed']);
        $this->assertEmpty($result['discrepancies']);
    }

    public function test_audit_loyalty_model_c_pts_detects_wallet_lot_cache_and_global_mismatch(): void
    {
        InvoiceLine::query()->delete();
        Invoice::query()->delete();

        $user = User::factory()->create();

        PointBatch::create([
            'user_id' => $user->id,
            'points_earned' => 200,
            'points_remaining' => 200, // Sum 200 mismatch with wallet (300)
            'earned_at' => now(),
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        LedgerAccount::create([
            'code' => "points:user:{$user->id}:PTS",
            'name' => "User {$user->id} PTS Wallet",
            'kind' => 'LIABILITY',
            'asset_code' => 'PTS',
            'cached_balance' => '300',
        ]);

        // Global PTS accounts produce expected sum 0 != 300
        LedgerAccount::updateOrCreate(
            ['code' => MallAccounts::EXPENSE_POINTS_ISSUED_PTS],
            ['name' => 'Points Issued Expense', 'kind' => 'EXPENSE', 'asset_code' => 'PTS', 'cached_balance' => '0']
        );
        LedgerAccount::updateOrCreate(
            ['code' => MallAccounts::REVENUE_POINTS_REDEEMED_PTS],
            ['name' => 'Points Redeemed Revenue', 'kind' => 'REVENUE', 'asset_code' => 'PTS', 'cached_balance' => '0']
        );
        LedgerAccount::updateOrCreate(
            ['code' => MallAccounts::REVENUE_POINTS_BREAKAGE_PTS],
            ['name' => 'Points Breakage Revenue', 'kind' => 'REVENUE', 'asset_code' => 'PTS', 'cached_balance' => '0']
        );

        $query = new AuditBillingQuery;
        $result = $query->execute();

        $this->assertFalse($result['passed']);
        $discrepancyText = implode("\n", $result['discrepancies']);
        $this->assertStringContainsString('Points discrepancy: wallet balance (300) != active lots sum (200)', $discrepancyText);
        $this->assertStringContainsString('Global PTS reconciliation failed', $discrepancyText);
    }
}
