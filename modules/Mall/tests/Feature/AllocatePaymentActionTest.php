<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mall\Application\Actions\AllocatePaymentAction;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\InvoiceLine;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Tenant;
use Tests\TestCase;

class AllocatePaymentActionTest extends TestCase
{
    use RefreshDatabase;

    private User $tenantUser;

    private Tenant $tenant;

    private Lease $lease;

    private AllocatePaymentAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(MallSeeder::class);

        $this->tenantUser = User::firstOrCreate(
            ['email' => 'tenant.sariranah@duttamall.com'],
            ['name' => 'Sari Ranah Owner', 'role' => 'tenant', 'password' => bcrypt('password')]
        );

        $this->tenant = Tenant::where('brand_name', 'RM Sari Ranah')->firstOrFail();
        $this->tenant->update(['user_id' => $this->tenantUser->id]);

        $this->lease = Lease::where('tenant_id', $this->tenant->id)->firstOrFail();

        // Top up tenant wallet with 50,000,000 IDR
        $this->tenantUser->walletAccount('IDR');
        app(TopUpAction::class)->execute($this->tenantUser, 50000000, 'Top up for billing tests');

        $this->action = app(AllocatePaymentAction::class);
    }

    public function test_rejects_non_positive_payment_amount(): void
    {
        $invoice = Invoice::first();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nominal pembayaran harus lebih besar dari 0.');

        $this->action->execute($invoice, 0, 'portal', 'test-key-zero');
    }

    public function test_rejects_payment_exceeding_remaining_amount(): void
    {
        $invoice = Invoice::first();
        $remaining = $invoice->remainingAmount();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('melebihi sisa tagihan');

        $this->action->execute($invoice, $remaining + 1000, 'portal', 'test-key-excess');
    }

    public function test_rejects_payment_when_tenant_wallet_balance_insufficient(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-BIG-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2027-01',
            'subtotal' => 100000000,
            'penalty_amount' => 0,
            'total_amount' => 100000000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => now()->addDays(7),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa Besar',
            'quantity' => 1,
            'unit_price' => 100000000,
            'amount' => 100000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Saldo dompet tenant tidak mencukupi');

        // Payment 90 jt > 50 jt wallet
        $this->action->execute($invoice, 90000000, 'portal', 'test-key-insufficient');
    }

    public function test_rejects_missing_idempotency_key(): void
    {
        $invoice = Invoice::first();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency key wajib diisi untuk alokasi pembayaran invoice.');

        $this->action->execute($invoice, 10000, 'portal', null);
    }

    public function test_rejects_empty_or_whitespace_idempotency_key(): void
    {
        $invoice = Invoice::first();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency key wajib diisi untuk alokasi pembayaran invoice.');

        $this->action->execute($invoice, 10000, 'portal', '   ');
    }

    public function test_accepts_idempotency_key_from_request_headers_or_inputs(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-REQ-KEY-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2027-02',
            'subtotal' => 100000,
            'penalty_amount' => 0,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => now()->addDays(7),
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa Header',
            'quantity' => 1,
            'unit_price' => 100000,
            'amount' => 100000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        request()->headers->set('Idempotency-Key', 'from-header-key');
        $updated = $this->action->execute($invoice, 100000, 'portal', null);
        $this->assertSame('mall_pay_from-header-key', $updated->payment_reference);
        request()->headers->remove('Idempotency-Key');

        // Test with input idempotency_key
        request()->merge(['idempotency_key' => 'from-input-key']);
        $invoiceInput = Invoice::create([
            'invoice_number' => 'INV-REQ-KEY-002',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2027-03',
            'subtotal' => 100000,
            'penalty_amount' => 0,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => now()->addDays(7),
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoiceInput->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa Input',
            'quantity' => 1,
            'unit_price' => 100000,
            'amount' => 100000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);
        $updatedInput = $this->action->execute($invoiceInput, 100000, 'portal', null);
        $this->assertSame('mall_pay_from-input-key', $updatedInput->payment_reference);
        request()->replace([]);
    }

    public function test_replay_idempotency_key_returns_invoice_without_duplicate_posting(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-REPLAY-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2027-04',
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
            'description' => 'Sewa Replay',
            'quantity' => 1,
            'unit_price' => 1000000,
            'amount' => 1000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $firstRun = $this->action->execute($invoice, 500000, 'portal', 'idem-replay-test-1');
        $this->assertSame(InvoiceStatus::PARTIALLY_PAID, $firstRun->status);
        $this->assertSame(500000, $firstRun->paid_amount);

        $initialTxCount = LedgerTransaction::where('idempotency_key', 'mall_pay_idem-replay-test-1')->count();
        $this->assertSame(1, $initialTxCount);

        // Replay with exact same key
        $replayRun = $this->action->execute($invoice, 500000, 'portal', 'idem-replay-test-1');
        $this->assertSame(500000, $replayRun->paid_amount);

        // Transaction count should still be 1
        $afterTxCount = LedgerTransaction::where('idempotency_key', 'mall_pay_idem-replay-test-1')->count();
        $this->assertSame(1, $afterTxCount);
    }

    public function test_partial_payment_allocates_by_priority_and_updates_status(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PRIORITY-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2027-05',
            'subtotal' => 3000000,
            'penalty_amount' => 500000,
            'total_amount' => 3500000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => now()->addDays(7),
        ]);

        $linePenalty = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::PENALTY,
            'description' => 'Denda',
            'quantity' => 1,
            'unit_price' => 500000,
            'amount' => 500000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);
        $lineElec = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::ELECTRICITY,
            'description' => 'Listrik',
            'quantity' => 1,
            'unit_price' => 1000000,
            'amount' => 1000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);
        $lineRent = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa',
            'quantity' => 1,
            'unit_price' => 2000000,
            'amount' => 2000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        // Pay 1,000,000: covers 500,000 penalty, 500,000 electricity, 0 rent
        $updated = $this->action->execute($invoice, 1000000, 'portal', 'key-alloc-prio-1');

        $this->assertSame(InvoiceStatus::PARTIALLY_PAID, $updated->status);
        $this->assertSame(1000000, $updated->paid_amount);

        $this->assertSame(500000, $linePenalty->fresh()->paid_amount);
        $this->assertSame('paid', $linePenalty->fresh()->status);

        $this->assertSame(500000, $lineElec->fresh()->paid_amount);
        $this->assertSame('partially_paid', $lineElec->fresh()->status);

        $this->assertSame(0, $lineRent->fresh()->paid_amount);
        $this->assertSame('unpaid', $lineRent->fresh()->status);
    }

    public function test_full_payment_completes_invoice_and_restores_suspended_lease(): void
    {
        $this->lease->update(['status' => LeaseStatus::SUSPENDED]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-FULL-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2027-06',
            'subtotal' => 2000000,
            'penalty_amount' => 0,
            'total_amount' => 2000000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::OVERDUE,
            'due_date' => now()->subDays(5),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa Penuh',
            'quantity' => 1,
            'unit_price' => 2000000,
            'amount' => 2000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $updated = $this->action->execute($invoice, 2000000, 'portal', 'key-full-payment-1');

        $this->assertSame(InvoiceStatus::PAID, $updated->status);
        $this->assertSame(2000000, $updated->paid_amount);
        $this->assertNotNull($updated->paid_at);
        $this->assertSame('mall_pay_key-full-payment-1', $updated->payment_reference);

        // Verify ledger transaction, splits, and accounts
        $tx = LedgerTransaction::where('idempotency_key', 'mall_pay_key-full-payment-1')->firstOrFail();
        $this->assertSame('Pembayaran Tagihan Mall INV-FULL-001 (portal)', $tx->description);
        $meta = is_array($tx->meta) ? $tx->meta : json_decode($tx->meta, true);
        $this->assertSame('portal', $meta['source']);
        $this->assertSame((string) 2000000, $meta['amount']);
        $this->assertSame($invoice->id, $meta['invoice_id']);
        $this->assertSame($this->tenant->id, $meta['tenant_id']);
        $this->assertArrayHasKey('revenue:mall:rent:IDR', $meta['splits']);
        $this->assertSame(2000000, $meta['splits']['revenue:mall:rent:IDR']);

        // Check entries created: debit wallet (-2000000), credit revenue (+2000000)
        $this->assertCount(2, $tx->entries);
        $debitEntry = $tx->entries->first(fn ($e) => bccomp($e->amount, '-2000000') === 0);
        $creditEntry = $tx->entries->first(fn ($e) => bccomp($e->amount, '2000000') === 0);
        $this->assertNotNull($debitEntry);
        $this->assertNotNull($creditEntry);
        $this->assertSame(48000000, (int) $this->tenantUser->walletAccount('IDR')->cached_balance);
        $this->assertSame(0, bccomp($debitEntry->amount, '-2000000'));
        $this->assertSame(0, bccomp($creditEntry->amount, '2000000'));
        $this->assertSame('revenue:mall:rent:IDR', $creditEntry->account->code);

        // Verify relationships loaded
        $this->assertTrue($updated->relationLoaded('lines'));
        $this->assertTrue($updated->relationLoaded('tenant'));
        $this->assertTrue($updated->relationLoaded('lease'));

        // Because no other overdue invoices exist for this lease, lease status becomes ACTIVE
        $this->assertSame(LeaseStatus::ACTIVE, $this->lease->fresh()->status);
    }

    public function test_full_payment_keeps_lease_suspended_if_other_overdue_exists(): void
    {
        $this->lease->update(['status' => LeaseStatus::SUSPENDED]);

        $invoice1 = Invoice::create([
            'invoice_number' => 'INV-SUSPEND-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2027-07',
            'subtotal' => 1000000,
            'penalty_amount' => 0,
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::OVERDUE,
            'due_date' => now()->subDays(15),
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice1->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa 1',
            'quantity' => 1,
            'unit_price' => 1000000,
            'amount' => 1000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        // Invoice 2 also overdue
        Invoice::create([
            'invoice_number' => 'INV-SUSPEND-002',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->lease->property_id,
            'period_month' => '2027-08',
            'subtotal' => 1000000,
            'penalty_amount' => 0,
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::OVERDUE,
            'due_date' => now()->subDays(5),
        ]);

        $this->action->execute($invoice1, 1000000, 'portal', 'key-suspend-pay-1');

        // Invoice 1 is paid, but Invoice 2 is still overdue -> lease remains SUSPENDED
        $this->assertSame(LeaseStatus::SUSPENDED, $this->lease->fresh()->status);
    }
}
