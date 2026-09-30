<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Mall\Application\Actions\AllocatePaymentAction;
use Modules\Mall\Application\Actions\BillWorkOrderToTenantAction;
use Modules\Mall\Application\Actions\ClaimReceiptPointsAction;
use Modules\Mall\Application\Actions\CreateEventBookingAction;
use Modules\Mall\Application\Actions\ExpirePointsAction;
use Modules\Mall\Application\Actions\RedeemVoucherAction;
use Modules\Mall\Application\Actions\UseVoucherAction;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\AssetCategory;
use Modules\Mall\Domain\Enums\AssetStatus;
use Modules\Mall\Domain\Enums\EventBookingStatus;
use Modules\Mall\Domain\Enums\EventType;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\LoyaltyTier;
use Modules\Mall\Domain\Enums\ReceiptClaimStatus;
use Modules\Mall\Domain\Enums\RentModel;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Mall\Domain\Enums\VoucherStatus;
use Modules\Mall\Domain\Enums\WorkOrderPriority;
use Modules\Mall\Domain\Enums\WorkOrderStatus;
use Modules\Mall\Domain\Enums\WorkOrderType;
use Modules\Mall\Domain\Exceptions\DuplicateReceiptClaimException;
use Modules\Mall\Domain\Exceptions\EventScheduleConflictException;
use Modules\Mall\Domain\Exceptions\InsufficientPointsException;
use Modules\Mall\Domain\Exceptions\InvalidVoucherException;
use Modules\Mall\Domain\Models\Asset;
use Modules\Mall\Domain\Models\EventSpace;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\LoyaltyMember;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\ReceiptClaim;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;
use Modules\Mall\Domain\Models\Voucher;
use Modules\Mall\Domain\Models\VoucherTemplate;
use Modules\Mall\Domain\Models\WorkOrder;
use Tests\TestCase;

class MallLoyaltyAndFacilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected User $tenantUser;

    protected Property $property;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(MallSeeder::class);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@mall.com'],
            ['name' => 'Mall Director', 'role' => 'admin', 'password' => bcrypt('password')]
        );
        $this->customer = User::firstOrCreate(
            ['email' => 'customer@mall.com'],
            ['name' => 'Customer Demo', 'role' => 'customer', 'password' => bcrypt('password')]
        );
        $this->tenantUser = User::firstOrCreate(
            ['email' => 'tenant.sariranah@duttamall.com'],
            ['name' => 'Sari Ranah Owner', 'role' => 'tenant', 'password' => bcrypt('password')]
        );

        $this->property = Property::first();
        $this->tenant = Tenant::first();
        $this->tenant->update(['user_id' => $this->tenantUser->id]);
    }

    public function test_earn_poin_belanja_dari_klaim_struk_dengan_tier_multiplier_dan_double_entry_pts(): void
    {
        $action = app(ClaimReceiptPointsAction::class);

        // Buat member dengan tier Gold (1.5x)
        $member = LoyaltyMember::updateOrCreate(
            ['user_id' => $this->customer->id],
            [
                'tier' => LoyaltyTier::GOLD,
                'lifetime_spend' => 12_000_000,
                'current_year_spend' => 12_000_000,
            ]
        );

        $receiptNumber = 'STR-EXP-'.date('YmdHis');
        // Belanja Rp 150.000 -> Base = 15 poin * 1.5x multiplier = 22 PTS
        $claim = $action->execute(
            user: $this->customer,
            receiptNumber: $receiptNumber,
            receiptAmount: 150_000,
            receiptDate: Carbon::today(),
            tenantId: $this->tenant->id,
            processor: $this->admin
        );

        $this->assertInstanceOf(ReceiptClaim::class, $claim);
        $this->assertSame(22, $claim->points_earned);
        $this->assertSame(ReceiptClaimStatus::APPROVED, $claim->status);

        // Verifikasi saldo PTS pengguna
        $this->assertSame(22, $this->customer->pointsBalance());

        // Verifikasi PointBatch untuk FIFO
        $batch = PointBatch::where('user_id', $this->customer->id)->latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertSame(22, $batch->points_remaining);
        $this->assertFalse($batch->is_expired);

        // Verifikasi buku besar seimbang untuk aset PTS
        $this->assertSame(0, Artisan::call('bank:reconcile'), 'Rekonsiliasi double-entry ledger PTS tidak bersih.');
    }

    public function test_klaim_struk_dengan_nomor_yang_sama_ditolak_dengan_exception(): void
    {
        $action = app(ClaimReceiptPointsAction::class);
        $receiptNumber = 'STR-DUPLICATE-001';

        $action->execute(
            user: $this->customer,
            receiptNumber: $receiptNumber,
            receiptAmount: 100_000,
            receiptDate: Carbon::today(),
            tenantId: $this->tenant->id
        );

        $this->expectException(DuplicateReceiptClaimException::class);

        $action->execute(
            user: $this->customer,
            receiptNumber: $receiptNumber,
            receiptAmount: 100_000,
            receiptDate: Carbon::today(),
            tenantId: $this->tenant->id
        );
    }

    public function test_redeem_voucher_memotong_poin_secara_fifo_dan_menerbitkan_liabilitas_idr(): void
    {
        $claimAction = app(ClaimReceiptPointsAction::class);
        $redeemAction = app(RedeemVoucherAction::class);

        // User belanja Rp 600.000 (Tier Silver: 1.0x -> 60 PTS)
        $claimAction->execute(
            user: $this->customer,
            receiptNumber: 'STR-EARN-60PTS',
            receiptAmount: 600_000,
            receiptDate: Carbon::today(),
            tenantId: $this->tenant->id
        );

        $this->assertSame(60, $this->customer->pointsBalance());

        // Tukar voucher 50 PTS (Rp 50.000)
        $template = VoucherTemplate::where('points_required', 50)->firstOrFail();
        $voucher = $redeemAction->execute($this->customer, $template);

        $this->assertInstanceOf(Voucher::class, $voucher);
        $this->assertSame(VoucherStatus::ACTIVE, $voucher->status);
        $this->assertSame(50_000, $voucher->nominal_value);
        $this->assertTrue(str_starts_with($voucher->voucher_code, 'VCH-'));

        // Sisa poin harus 10 PTS
        $this->assertSame(10, $this->customer->pointsBalance());

        // Verifikasi saldo PointBatch terpotong FIFO
        $remainingInBatches = (int) PointBatch::where('user_id', $this->customer->id)->sum('points_remaining');
        $this->assertSame(10, $remainingInBatches);

        // Rekonsiliasi ledger (PTS dan IDR) harus tetap seimbang
        $this->assertSame(0, Artisan::call('bank:reconcile'));
    }

    public function test_redeem_voucher_gagal_bila_poin_tidak_mencukupi(): void
    {
        $redeemAction = app(RedeemVoucherAction::class);
        $template = VoucherTemplate::where('points_required', 100)->firstOrFail();

        $newUser = User::factory()->create(['role' => 'customer']);

        $this->expectException(InsufficientPointsException::class);
        $redeemAction->execute($newUser, $template);
    }

    public function test_voucher_dipakai_di_tenant_lalu_disettle_via_command_ke_wallet_tenant(): void
    {
        // 1. Berikan poin dan terbitkan voucher untuk customer
        $claimAction = app(ClaimReceiptPointsAction::class);
        $redeemAction = app(RedeemVoucherAction::class);
        $useAction = app(UseVoucherAction::class);

        $claimAction->execute($this->customer, 'STR-VCH-USE', 500_000, Carbon::today());
        $template = VoucherTemplate::where('points_required', 25)->firstOrFail();
        $voucher = $redeemAction->execute($this->customer, $template);

        // 2. Customer memakai voucher di tenant (Belanja Rp 150.000)
        $usedVoucher = $useAction->execute(
            voucherCode: $voucher->voucher_code,
            tenant: $this->tenant,
            transactionAmount: 150_000,
            transactionRef: 'TRX-POS-1001'
        );

        $this->assertSame(VoucherStatus::USED, $usedVoucher->status);
        $this->assertSame($this->tenant->id, $usedVoucher->used_at_tenant_id);
        $this->assertNull($usedVoucher->settled_at);

        // Saldo awal wallet tenant sebelum settlement
        $initialTenantBalance = (int) $this->tenantUser->walletAccount('IDR')->cached_balance;

        // 3. Jalankan command mall:settle-vouchers
        Artisan::call('mall:settle-vouchers');

        $usedVoucher->refresh();
        $this->assertSame(VoucherStatus::SETTLED, $usedVoucher->status);
        $this->assertNotNull($usedVoucher->settled_at);
        $this->assertNotNull($usedVoucher->settlement_id);

        // 4. Saldo dompet tenant bertambah sebesar nominal voucher (Rp 25.000)
        $this->tenantUser->walletAccount('IDR')->refresh();
        $finalTenantBalance = (int) $this->tenantUser->walletAccount('IDR')->fresh()->cached_balance;
        $this->assertSame($initialTenantBalance + 25_000, $finalTenantBalance);

        // Rekonsiliasi ledger tetap bersih
        $this->assertSame(0, Artisan::call('bank:reconcile'));
    }

    public function test_pemakaian_voucher_yang_tidak_valid_ditolak(): void
    {
        $useAction = app(UseVoucherAction::class);

        // Kode tidak ada
        $this->expectException(InvalidVoucherException::class);
        $useAction->execute('VCH-INVALID-CODE', $this->tenant, 150_000);
    }

    public function test_voucher_kedaluwarsa_dialihkan_ke_pendapatan_breakage(): void
    {
        // Buat voucher aktif yang tanggal kedaluwarsanya di masa lampau
        $template = VoucherTemplate::first();
        $expiredVoucher = Voucher::create([
            'voucher_code' => 'VCH-EXPIRED-TEST',
            'template_id' => $template->id,
            'user_id' => $this->customer->id,
            'nominal_value' => 50_000,
            'min_spend' => 100_000,
            'status' => VoucherStatus::ACTIVE,
            'expires_at' => Carbon::now()->subDays(2),
        ]);

        Artisan::call('mall:expire-vouchers');

        $expiredVoucher->refresh();
        $this->assertSame(VoucherStatus::EXPIRED, $expiredVoucher->status);

        $this->assertSame(0, Artisan::call('bank:reconcile'));
    }

    public function test_poin_loyalitas_kedaluwarsa_dibalik_secara_fifo_via_command(): void
    {
        // User memperoleh 30 poin via klaim struk belanja sah (membukukan ledger resmi)
        $claimAction = app(ClaimReceiptPointsAction::class);
        $claimAction->execute($this->customer, 'STR-EXP-FIFO', 300_000, Carbon::today());

        $this->assertSame(30, $this->customer->pointsBalance());

        // Ubah masa aktif batch ke masa lampau
        PointBatch::where('user_id', $this->customer->id)->update([
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $action = app(ExpirePointsAction::class);
        $expiredPoints = $action->execute();

        $this->assertSame(30, $expiredPoints);
        $this->assertSame(0, $this->customer->pointsBalance());

        $batch = PointBatch::where('user_id', $this->customer->id)->latest('id')->first();
        $this->assertSame(0, $batch->points_remaining);
        $this->assertTrue($batch->is_expired);

        $this->assertSame(0, Artisan::call('bank:reconcile'));
    }

    public function test_pemesanan_event_atrium_pada_jadwal_bentrok_ditolak(): void
    {
        $space = EventSpace::where('code', 'ATR-MAIN')->firstOrFail();
        $action = app(CreateEventBookingAction::class);

        // Booking 1: 10 Okt s/d 15 Okt (Terkonfirmasi)
        $booking1 = $action->execute(
            space: $space,
            customer: $this->customer,
            eventName: 'Pameran Buku Gramedia',
            eventType: EventType::EXHIBITION,
            startDate: '2026-10-10',
            endDate: '2026-10-15'
        );
        $booking1->update(['status' => EventBookingStatus::CONFIRMED]);

        // Booking 2: 12 Okt s/d 18 Okt (Bentrok)
        $this->expectException(EventScheduleConflictException::class);

        $action->execute(
            space: $space,
            customer: $this->customer,
            eventName: 'Konser Musik Akhir Pekan',
            eventType: EventType::CONCERT,
            startDate: '2026-10-12',
            endDate: '2026-10-18'
        );
    }

    public function test_command_mall_generate_pm_orders_menerbitkan_spk_untuk_aset_jatuh_tempo(): void
    {
        $asset = Asset::create([
            'property_id' => $this->property->id,
            'asset_tag' => 'AST-TEST-HVAC-99',
            'name' => 'Exhaust Fan Koridor Barat',
            'category' => AssetCategory::HVAC,
            'pm_frequency_days' => 30,
            'status' => AssetStatus::OPERATIONAL,
            'last_pm_date' => Carbon::today()->subDays(35),
            'next_pm_date' => Carbon::today()->subDays(5), // Jatuh tempo 5 hari lalu
        ]);

        Artisan::call('mall:generate-pm-orders');

        // Verifikasi Work Order preventif terbit
        $wo = WorkOrder::where('asset_id', $asset->id)->first();
        $this->assertNotNull($wo);
        $this->assertSame(WorkOrderType::PREVENTIVE, $wo->type);
        $this->assertSame(WorkOrderStatus::OPEN, $wo->status);

        // Tanggal PM berikutnya harus dimajukan 30 hari ke depan
        $asset->refresh();
        $this->assertSame(Carbon::today()->addDays(30)->toDateString(), $asset->next_pm_date->toDateString());
    }

    public function test_work_order_mendeteksi_pelanggaran_sla(): void
    {
        $wo = WorkOrder::create([
            'property_id' => $this->property->id,
            'order_number' => 'WO-SLA-TEST-001',
            'type' => WorkOrderType::EMERGENCY,
            'priority' => WorkOrderPriority::EMERGENCY,
            'title' => 'Pipa Utama Pecah di Toilet Lantai 2',
            'status' => WorkOrderStatus::OPEN,
            'sla_hours' => 2,
            'due_date' => Carbon::now()->subHours(3), // Terlewat 3 jam
            'sla_breached' => false,
        ]);

        $this->assertTrue($wo->checkSlaBreach());
        $this->assertTrue($wo->fresh()->sla_breached);
    }

    public function test_biaya_perbaikan_work_order_ditagihkan_ke_invoice_tenant(): void
    {
        $action = app(BillWorkOrderToTenantAction::class);

        // Buat tenant baru yang belum memiliki tagihan berjalan sebelumnya
        $boutiqueUser = User::create([
            'name' => 'Boutique Owner',
            'email' => 'boutique@mall.com',
            'role' => 'tenant',
            'password' => bcrypt('password'),
        ]);

        $boutiqueTenant = Tenant::create([
            'user_id' => $boutiqueUser->id,
            'company_name' => 'PT Duta Mode Busana',
            'brand_name' => 'Duta Boutique',
            'pic_name' => 'Boutique Manager',
            'pic_phone' => '081234567890',
            'pic_email' => 'boutique@mall.com',
            'category' => TenantCategory::FASHION,
            'is_active' => true,
        ]);

        $unit = Unit::where('status', UnitStatus::AVAILABLE)->first()
            ?? Unit::first();

        Lease::create([
            'property_id' => $this->property->id,
            'unit_id' => $unit->id,
            'tenant_id' => $boutiqueTenant->id,
            'lease_number' => 'LSE-TEST-BOUTIQUE-01',
            'rent_model' => RentModel::FIXED,
            'start_date' => Carbon::now()->subMonth()->toDateString(),
            'end_date' => Carbon::now()->addYear()->toDateString(),
            'base_monthly_rent' => 10_000_000,
            'service_charge_monthly' => 1_000_000,
            'billing_day' => 1,
            'grace_days' => 7,
            'penalty_rate_daily_percent' => 0.10,
            'security_deposit_amount' => 30_000_000,
            'status' => LeaseStatus::ACTIVE,
            'activated_at' => Carbon::now()->subMonth(),
        ]);

        $workOrder = WorkOrder::create([
            'property_id' => $this->property->id,
            'tenant_id' => $boutiqueTenant->id,
            'order_number' => 'WO-TENANT-REPAIR-01',
            'type' => WorkOrderType::TENANT_REQUEST,
            'priority' => WorkOrderPriority::MEDIUM,
            'title' => 'Penggantian Saklar & MCB Tenant',
            'status' => WorkOrderStatus::COMPLETED,
            'parts_cost' => 300_000,
            'labor_cost' => 150_000,
            'total_cost' => 450_000,
            'is_billable_to_tenant' => true,
        ]);

        $invoice = $action->execute($workOrder);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame($invoice->id, $workOrder->fresh()->billed_invoice_id);

        // Verifikasi baris tagihan biaya perbaikan ada di invoice
        $repairLine = $invoice->lines()->where('type', InvoiceLineType::REPAIR_COST)->first();
        $this->assertNotNull($repairLine);
        $this->assertSame(450_000, $repairLine->amount);

        // Pelunasan tagihan via AllocatePaymentAction
        app(TopUpAction::class)->execute($boutiqueUser, 1_000_000);
        $allocateAction = app(AllocatePaymentAction::class);
        $paidInvoice = $allocateAction->execute($invoice, 450_000);

        $this->assertSame(450_000, $repairLine->fresh()->paid_amount);
        $this->assertSame(0, Artisan::call('bank:reconcile'));
    }

    public function test_halaman_loyalty_events_dan_facilities_dapat_diakses_via_http(): void
    {
        $this->actingAs($this->admin);

        $responseLoyalty = $this->get(route('mall.loyalty.index'));
        $responseLoyalty->assertOk();
        $responseLoyalty->assertSeeText('Loyalty Duta Points & Voucher Mall');

        $responseEvents = $this->get(route('mall.events.index'));
        $responseEvents->assertOk();
        $responseEvents->assertSeeText('Sewa Atrium & Manajemen Event Mall');

        $responseFacilities = $this->get(route('mall.facilities.index'));
        $responseFacilities->assertOk();
        $responseFacilities->assertSeeText('Facility Management & Work Orders (SPK)');
    }
}
