<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Mall\Application\Actions\MallAutoDebitAction;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\InvoiceLine;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MallAutoDebitActionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $tenantUser;

    private Tenant $tenant;

    private Lease $lease;

    private MallAutoDebitAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(MallSeeder::class);

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin.mall@duttamall.com'],
            ['name' => 'Admin Mall', 'role' => 'admin', 'password' => bcrypt('password')]
        );

        $this->tenantUser = User::firstOrCreate(
            ['email' => 'tenant.autodebit@duttamall.com'],
            ['name' => 'AutoDebit Tenant', 'role' => 'tenant', 'password' => bcrypt('password')]
        );

        $this->tenant = Tenant::firstOrFail();
        $this->tenant->update(['user_id' => $this->tenantUser->id]);
        $this->lease = Lease::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->action = app(MallAutoDebitAction::class);
    }

    public function test_rejects_unauthorized_user(): void
    {
        $nonAdmin = User::create([
            'name' => 'Regular Customer',
            'email' => 'regular@duttamall.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($nonAdmin);

        $invoice = Invoice::first();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Hanya role admin atau mall_admin yang berhak menjalankan auto-debit tagihan mall.');

        $this->action->execute($invoice);
    }

    public function test_rejects_unauthorized_user_in_auto_debit_all(): void
    {
        $nonAdmin = User::create([
            'name' => 'Regular Customer 2',
            'email' => 'regular2@duttamall.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($nonAdmin);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Hanya role admin atau mall_admin yang berhak menjalankan auto-debit tagihan mall.');

        $this->action->autoDebitAll();
    }

    public function test_returns_failure_when_tenant_or_user_missing(): void
    {
        $this->actingAs($this->adminUser);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-NOTENANT-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2028-09',
            'subtotal' => 1000000,
            'penalty_amount' => 0,
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => now()->addDays(7),
        ]);

        $this->tenant->update(['user_id' => null]);
        $invoice->refresh();
        $res = $this->action->execute($invoice);
        $this->assertFalse($res['success']);
        $this->assertSame('Akun pengguna tenant tidak ditemukan.', $res['message']);

        // Restore tenant user
        $this->tenant->update(['user_id' => $this->tenantUser->id]);
    }

    public function test_returns_already_paid_when_remaining_is_zero(): void
    {
        $this->actingAs($this->adminUser);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-ZERO-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2028-01',
            'subtotal' => 1000000,
            'penalty_amount' => 0,
            'total_amount' => 1000000,
            'paid_amount' => 1000000,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDays(7),
        ]);

        $res = $this->action->execute($invoice);

        $this->assertTrue($res['success']);
        $this->assertSame(0, $res['debited']);
        $this->assertSame(0, $res['remaining']);
        $this->assertTrue($res['is_fully_paid']);
        $this->assertSame('Invoice sudah lunas.', $res['message']);
    }

    public function test_returns_failure_when_wallet_has_zero_balance(): void
    {
        $this->actingAs($this->adminUser);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-NOBAL-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2028-02',
            'subtotal' => 1000000,
            'penalty_amount' => 0,
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => now()->addDays(7),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa',
            'quantity' => 1,
            'unit_price' => 1000000,
            'amount' => 1000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        // Empty wallet
        $this->tenantUser->walletAccount('IDR');

        $res = $this->action->execute($invoice);

        $this->assertFalse($res['success']);
        $this->assertSame(0, $res['debited']);
        $this->assertSame(1000000, $res['remaining']);
        $this->assertFalse($res['is_fully_paid']);
        $this->assertStringContainsString('Saldo dompet tidak mencukupi', $res['message']);
    }

    public function test_executes_partial_and_full_auto_debit_successfully(): void
    {
        $this->actingAs($this->adminUser);

        $this->tenantUser->walletAccount('IDR');
        app(TopUpAction::class)->execute($this->tenantUser, 500000, 'Topup 500k for partial autodebit');

        $invoice = Invoice::create([
            'invoice_number' => 'INV-PARTIAL-AD-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2028-03',
            'subtotal' => 1000000,
            'penalty_amount' => 0,
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => now()->addDays(7),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa',
            'quantity' => 1,
            'unit_price' => 1000000,
            'amount' => 1000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        // 1. Partial auto debit with 500k wallet
        $resPartial = $this->action->execute($invoice, 'ad-key-1');
        $this->assertTrue($resPartial['success']);
        $this->assertSame(500000, $resPartial['debited']);
        $this->assertSame(500000, $resPartial['remaining']);
        $this->assertFalse($resPartial['is_fully_paid']);
        $this->assertStringContainsString('Auto-debit berhasil memotong saldo', $resPartial['message']);

        // 2. Top up another 1M and execute to fully pay
        app(TopUpAction::class)->execute($this->tenantUser, 1000000, 'Topup 1M for full autodebit');
        $resFull = $this->action->execute($invoice->fresh(), 'ad-key-2');
        $this->assertTrue($resFull['success']);
        $this->assertSame(500000, $resFull['debited']);
        $this->assertSame(0, $resFull['remaining']);
        $this->assertTrue($resFull['is_fully_paid']);
        $this->assertStringContainsString('Auto-debit berhasil melunasi tagihan', $resFull['message']);
    }

    public function test_auto_debit_all_processes_invoices_for_period(): void
    {
        $this->actingAs($this->adminUser);

        $this->tenantUser->walletAccount('IDR');
        app(TopUpAction::class)->execute($this->tenantUser, 2000000, 'Topup 2M for bulk autodebit');

        $inv1 = Invoice::create([
            'invoice_number' => 'INV-ALL-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2028-04',
            'subtotal' => 600000,
            'penalty_amount' => 0,
            'total_amount' => 600000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => now()->addDays(7),
        ]);
        InvoiceLine::create([
            'invoice_id' => $inv1->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa 1',
            'quantity' => 1,
            'unit_price' => 600000,
            'amount' => 600000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $summary = $this->action->autoDebitAll('2028-04');
        $this->assertSame(600000, $summary['total_debited']);
        $this->assertSame(1, $summary['processed_invoices']);
    }
}
