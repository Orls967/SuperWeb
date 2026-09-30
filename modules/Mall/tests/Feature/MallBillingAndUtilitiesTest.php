<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Mall\Application\Actions\AllocatePaymentAction;
use Modules\Mall\Application\Actions\ApplyLatePenaltiesAction;
use Modules\Mall\Application\Actions\GenerateMonthlyInvoicesAction;
use Modules\Mall\Application\Actions\PayInvoiceAction;
use Modules\Mall\Application\Queries\AuditBillingQuery;
use Modules\Mall\Application\Services\TenantSalesService;
use Modules\Mall\Application\Services\UtilityTariffCalculator;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\RentModel;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Mall\Domain\Enums\UtilityType;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\InvoiceLine;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;
use Tests\TestCase;

class MallBillingAndUtilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $tenantUser;

    protected User $otherTenantUser;

    protected Property $property;

    protected Tenant $tenant;

    protected Tenant $otherTenant;

    protected Lease $lease;

    protected Lease $otherLease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(MallSeeder::class);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@mall.com'],
            ['name' => 'Mall Director', 'role' => 'admin', 'password' => bcrypt('password')]
        );

        $this->tenantUser = User::firstOrCreate(
            ['email' => 'tenant.sariranah@duttamall.com'],
            ['name' => 'Sari Ranah Owner', 'role' => 'tenant', 'password' => bcrypt('password')]
        );

        $this->otherTenantUser = User::create([
            'name' => 'Other Tenant Owner',
            'email' => 'other.tenant@mall.com',
            'role' => 'tenant',
            'password' => bcrypt('password'),
        ]);

        // PIN dompet lewat mekanisme Banking (hash + lockout), bukan kolom users.pin
        foreach ([$this->tenantUser, $this->otherTenantUser] as $pinUser) {
            $pinUser->walletAccount('IDR');
            app(SetPinAction::class)->execute($pinUser, '123456');
        }

        $this->property = Property::where('code', 'DM-BJM')->firstOrFail();
        $this->tenant = Tenant::where('brand_name', 'RM Sari Ranah')->firstOrFail();
        $this->tenant->update(['user_id' => $this->tenantUser->id]);

        $this->otherTenant = Tenant::create([
            'company_name' => 'PT Toko Lain Sejahtera',
            'brand_name' => 'Toko Busana Lain',
            'pic_name' => 'Budi Sudarsono',
            'pic_phone' => '081299998888',
            'pic_email' => 'budi@tokolain.com',
            'category' => TenantCategory::FASHION,
            'user_id' => $this->otherTenantUser->id,
            'is_active' => true,
        ]);

        $this->lease = Lease::where('lease_number', 'LSE-DM-2026-001')->firstOrFail();

        $unitL1 = Unit::where('unit_number', 'L1-01')->firstOrFail();
        $this->otherLease = Lease::create([
            'lease_number' => 'LSE-DM-2026-999',
            'property_id' => $this->property->id,
            'unit_id' => $unitL1->id,
            'tenant_id' => $this->otherTenant->id,
            'rent_model' => RentModel::FIXED,
            'start_date' => Carbon::now()->subMonths(1)->toDateString(),
            'end_date' => Carbon::now()->addMonths(11)->toDateString(),
            'billing_day' => 1,
            'grace_days' => 7,
            'penalty_rate_daily_percent' => 0.10,
            'base_monthly_rent' => 15000000,
            'service_charge_monthly' => 3000000,
            'status' => LeaseStatus::ACTIVE,
            'activated_at' => Carbon::now()->subMonths(1),
        ]);
        $unitL1->update(['status' => UnitStatus::LEASED]);
    }

    public function test_generates_monthly_invoices_idempotently(): void
    {
        $action = app(GenerateMonthlyInvoicesAction::class);
        $period = '2026-11';

        // Panggilan pertama
        $inv1 = $action->generateForLease($this->lease, $period);
        $this->assertInstanceOf(Invoice::class, $inv1);
        $this->assertEquals($period, $inv1->period_month);
        $this->assertGreaterThan(0, $inv1->subtotal);
        $this->assertEquals(InvoiceStatus::ISSUED, $inv1->status);

        $initialLineCount = $inv1->lines()->count();
        $initialTotal = $inv1->total_amount;

        // Panggilan kedua (harus idempoten, tidak duplikasi invoice atau baris)
        $inv2 = $action->generateForLease($this->lease, $period);
        $this->assertEquals($inv1->id, $inv2->id);
        $this->assertEquals($initialTotal, $inv2->total_amount);
        $this->assertEquals($initialLineCount, $inv2->lines()->count());

        $this->assertEquals(1, Invoice::where('lease_id', $this->lease->id)->where('period_month', $period)->count());
    }

    public function test_calculates_revenue_share_topup_when_percent_exceeds_base_rent(): void
    {
        $salesService = app(TenantSalesService::class);
        $action = app(GenerateMonthlyInvoicesAction::class);

        // Unit LG-12 Sari Ranah: base_monthly_rent = 42.000.000, rev_share = 8%
        $baseRent = $this->lease->base_monthly_rent; // 42.000.000
        $this->assertEquals(42000000, $baseRent);

        // Case A: Omzet bersih Rp 400.000.000 -> 8% = Rp 32.000.000 (Kurang dari minimum Rp 42.000.000)
        // Maka hanya ada sewa pokok minimum, TANPA baris revenue_share_topup
        $periodA = '2026-11';
        $salesService->recordManualSales($this->lease, $periodA, 450000000, 400000000, 3000);
        $invA = $action->generateForLease($this->lease, $periodA);

        $baseRentLineA = $invA->lines()->where('type', InvoiceLineType::BASE_RENT)->first();
        $topupLineA = $invA->lines()->where('type', InvoiceLineType::REVENUE_SHARE_TOPUP)->first();

        $this->assertNotNull($baseRentLineA);
        $this->assertEquals(42000000, $baseRentLineA->amount);
        $this->assertNull($topupLineA); // Tidak ada topup karena rev share < min

        // Case B: Omzet bersih Rp 700.000.000 -> 8% = Rp 56.000.000 (Lebih besar dari minimum Rp 42.000.000)
        // Maka sewa pokok = 42.000.000 dan ada revenue_share_topup = 14.000.000 (Total sewa = 56.000.000)
        $periodB = '2026-12';
        $salesService->recordManualSales($this->lease, $periodB, 750000000, 700000000, 4500);
        $invB = $action->generateForLease($this->lease, $periodB);

        $baseRentLineB = $invB->lines()->where('type', InvoiceLineType::BASE_RENT)->first();
        $topupLineB = $invB->lines()->where('type', InvoiceLineType::REVENUE_SHARE_TOPUP)->first();

        $this->assertNotNull($baseRentLineB);
        $this->assertEquals(42000000, $baseRentLineB->amount);
        $this->assertNotNull($topupLineB);
        $this->assertEquals(14000000, $topupLineB->amount); // 56.000.000 - 42.000.000 = 14.000.000

        // Total komponen sewa = 56.000.000
        $this->assertEquals(56000000, $baseRentLineB->amount + $topupLineB->amount);
    }

    public function test_tiered_utility_tariffs_calculation(): void
    {
        $calculator = app(UtilityTariffCalculator::class);

        // Skema Listrik Duta Mall yang di-seed:
        // Tier 1: 0 - 500 kWh @ Rp 1.500
        // Tier 2: 500 - 2000 kWh @ Rp 1.800
        // Tier 3: > 2000 kWh @ Rp 2.200
        // Standing charge: Rp 100.000
        // Untuk usage 750 kWh:
        // Standing: 100.000
        // Tier 1 (500 kWh): 500 * 1500 = 750.000
        // Tier 2 (250 kWh): 250 * 1800 = 450.000
        // Total: 100.000 + 750.000 + 450.000 = 1.300.000
        $elecAmount = $calculator->calculate($this->property->id, UtilityType::ELECTRICITY, 750.0);
        $this->assertEquals(1300000, $elecAmount);

        // Skema Air PDAM Duta Mall yang di-seed:
        // Tier 1: 0 - 50 m3 @ Rp 8.000
        // Tier 2: > 50 m3 @ Rp 12.000
        // Standing charge: Rp 50.000
        // Untuk usage 80 m3:
        // Standing: 50.000
        // Tier 1 (50 m3): 50 * 8000 = 400.000
        // Tier 2 (30 m3): 30 * 12000 = 360.000
        // Total: 50.000 + 400.000 + 360.000 = 810.000
        $waterAmount = $calculator->calculate($this->property->id, UtilityType::WATER, 80.0);
        $this->assertEquals(810000, $waterAmount);
    }

    public function test_partial_payment_allocates_in_priority_order_penalty_utility_service_rent(): void
    {
        $topUp = app(TopUpAction::class);
        $topUp->execute($this->tenantUser, 10000000); // 10 jt

        // Buat invoice buatan dengan semua jenis baris tagihan
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-PRIORITY-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->property->id,
            'period_month' => '2026-11',
            'subtotal' => 6000000,
            'penalty_amount' => 500000,
            'total_amount' => 6500000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => Carbon::now()->subDays(5)->toDateString(),
        ]);

        $linePenalty = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::PENALTY,
            'description' => 'Denda Keterlambatan',
            'quantity' => 1,
            'unit_price' => 500000,
            'amount' => 500000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $lineWater = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::WATER,
            'description' => 'Tagihan Air',
            'quantity' => 1,
            'unit_price' => 300000,
            'amount' => 300000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $lineElec = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::ELECTRICITY,
            'description' => 'Tagihan Listrik',
            'quantity' => 1,
            'unit_price' => 700000,
            'amount' => 700000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $lineSC = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::SERVICE_CHARGE,
            'description' => 'Service Charge',
            'quantity' => 1,
            'unit_price' => 1500000,
            'amount' => 1500000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $lineRent = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa Pokok',
            'quantity' => 1,
            'unit_price' => 3500000,
            'amount' => 3500000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        // Tenant membayar sebagian: Rp 1.200.000
        // Urutan prioritas:
        // 1. Denda: butuh 500.000 -> lunas (sisa bayar 700.000)
        // 2. Air: butuh 300.000 -> lunas (sisa bayar 400.000)
        // 3. Listrik: butuh 700.000 -> terbayar 400.000 (sisa bayar 0)
        // 4. Service charge: 0
        // 5. Sewa pokok: 0
        $allocator = app(AllocatePaymentAction::class);
        $updated = $allocator->execute($invoice, 1200000, 'test_portal');

        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID, $updated->status);
        $this->assertEquals(1200000, $updated->paid_amount);
        $this->assertEquals(5300000, $updated->remainingAmount());

        $this->assertEquals(500000, $linePenalty->fresh()->paid_amount);
        $this->assertEquals('paid', $linePenalty->fresh()->status);

        $this->assertEquals(300000, $lineWater->fresh()->paid_amount);
        $this->assertEquals('paid', $lineWater->fresh()->status);

        $this->assertEquals(400000, $lineElec->fresh()->paid_amount);
        $this->assertEquals('partially_paid', $lineElec->fresh()->status);

        $this->assertEquals(0, $lineSC->fresh()->paid_amount);
        $this->assertEquals('unpaid', $lineSC->fresh()->status);

        $this->assertEquals(0, $lineRent->fresh()->paid_amount);
        $this->assertEquals('unpaid', $lineRent->fresh()->status);

        // Verifikasi pembukuan double-entry seimbang (sum == 0)
        $idrDiff = LedgerEntry::where('asset_code', 'IDR')->sum('amount');
        $this->assertEquals(0, (float) $idrDiff);
    }

    public function test_daily_penalty_applied_on_overdue_invoice(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-PENALTY-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->property->id,
            'period_month' => '2026-08',
            'subtotal' => 10000000, // 10 jt
            'penalty_amount' => 0,
            'total_amount' => 10000000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => Carbon::now()->subDays(10)->toDateString(), // 10 hari lewat tempo
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa Pokok',
            'quantity' => 1,
            'unit_price' => 10000000,
            'amount' => 10000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $action = app(ApplyLatePenaltiesAction::class);
        $result = $action->execute();

        $this->assertGreaterThan(0, $result['penalized_invoices']);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::OVERDUE, $invoice->status);

        // Denda harian: 0.10% * 10 jt = 10.000/hari * 10 hari = 100.000
        $this->assertEquals(100000, $invoice->penalty_amount);
        $this->assertEquals(10100000, $invoice->total_amount);

        $penaltyLine = $invoice->lines()->where('type', InvoiceLineType::PENALTY)->first();
        $this->assertNotNull($penaltyLine);
        $this->assertEquals(100000, $penaltyLine->amount);
    }

    public function test_lease_suspended_when_overdue_exceeds_30_days(): void
    {
        $this->assertEquals(LeaseStatus::ACTIVE, $this->lease->status);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-SUSPEND-001',
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'property_id' => $this->property->id,
            'period_month' => '2026-07',
            'subtotal' => 10000000,
            'penalty_amount' => 0,
            'total_amount' => 10000000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => Carbon::now()->subDays(35)->toDateString(), // 35 hari lewat tempo (> 30 hari)
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa Pokok Tertunggak',
            'quantity' => 1,
            'unit_price' => 10000000,
            'amount' => 10000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $action = app(ApplyLatePenaltiesAction::class);
        $result = $action->execute();

        $this->assertGreaterThan(0, $result['suspended_leases']);

        $this->lease->refresh();
        $this->assertEquals(LeaseStatus::SUSPENDED, $this->lease->status);
    }

    public function test_tenant_portal_idor_isolation(): void
    {
        // Buat invoice milik Tenant A (Sari Ranah)
        $invoiceA = Invoice::where('tenant_id', $this->tenant->id)->firstOrFail();

        // Buat invoice milik Tenant B (Other Tenant)
        $invoiceB = Invoice::create([
            'invoice_number' => 'INV-OTHER-999',
            'lease_id' => $this->otherLease->id,
            'tenant_id' => $this->otherTenant->id,
            'property_id' => $this->property->id,
            'period_month' => '2026-09',
            'subtotal' => 18000000,
            'penalty_amount' => 0,
            'total_amount' => 18000000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoiceB->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa Toko Lain',
            'quantity' => 1,
            'unit_price' => 18000000,
            'amount' => 18000000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        // Skenario 1: Tenant A mengakses invoice miliknya sendiri -> OK (200)
        $responseSelf = $this->actingAs($this->tenantUser)->get(route('mall.portal.invoice', $invoiceA->id));
        $responseSelf->assertStatus(200);

        // Skenario 2: Tenant A mencoba membuka invoice milik Tenant B -> DITOLAK (403 IDOR)
        $responseIdor = $this->actingAs($this->tenantUser)->get(route('mall.portal.invoice', $invoiceB->id));
        $responseIdor->assertStatus(403);

        // Skenario 3: Tenant A mencoba membayar invoice milik Tenant B -> DITOLAK (403 IDOR)
        $responsePayIdor = $this->actingAs($this->tenantUser)->post(route('mall.portal.pay', $invoiceB->id), [
            'amount' => 5000000,
            'pin' => '123456',
        ]);
        $responsePayIdor->assertStatus(403);

        // Skenario 4: Tenant A mencoba submit omzet untuk lease milik Tenant B -> DITOLAK (403 IDOR)
        $responseSalesIdor = $this->actingAs($this->tenantUser)->post(route('mall.portal.sales.store'), [
            'lease_id' => $this->otherLease->id,
            'period_month' => '2026-09',
            'gross_sales' => 50000000,
            'net_sales' => 45000000,
        ]);
        $responseSalesIdor->assertStatus(403);
    }

    public function test_audit_billing_command_and_ledger_reconciliation_clean(): void
    {
        // Top up tenant dan bayar sebagian tagihan via action
        $topUp = app(TopUpAction::class);
        $topUp->execute($this->tenantUser, 50000000); // 50 jt

        $invoice = Invoice::where('tenant_id', $this->tenant->id)->firstOrFail();
        $payAction = app(PayInvoiceAction::class);

        $payAction->execute($invoice, 20000000, '123456', $this->tenantUser); // Bayar 20 jt

        // Jalankan audit billing artisan
        $exitAudit = Artisan::call('mall:audit-billing');
        $this->assertEquals(0, $exitAudit);

        $auditResult = app(AuditBillingQuery::class)->execute();
        $this->assertTrue($auditResult['passed']);
        $this->assertGreaterThan(0, $auditResult['total_billed']);
        $this->assertGreaterThan(0, $auditResult['total_paid']);
        $this->assertGreaterThan(0, $auditResult['ledger_paid']);

        // Jalankan rekonsiliasi perbankan ledger global
        $exitReconcile = Artisan::call('bank:reconcile');
        $this->assertEquals(0, $exitReconcile);
    }
}
