<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Core\Application\Actions\TransferVehicleOwnershipAction;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Mall\Application\Actions\CheckInVehicleAction;
use Modules\Mall\Application\Actions\CheckOutVehicleAction;
use Modules\Mall\Application\Actions\ClaimReceiptPointsAction;
use Modules\Mall\Application\Actions\PayInvoiceAction;
use Modules\Mall\Application\Actions\RedeemVoucherAction;
use Modules\Mall\Application\Actions\RegisterParkingMemberAction;
use Modules\Mall\Application\Services\MallLedgerAccounts;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\MemberStatus;
use Modules\Mall\Domain\Enums\ParkingPaymentMethod;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Enums\VehicleType;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\VoucherTemplate;
use Modules\Resto\Application\Actions\AddOrderItemAction;
use Modules\Resto\Application\Actions\OpenShiftAction;
use Modules\Resto\Application\Actions\PayOrderAction;
use Modules\Resto\database\seeders\RestoMenuSeeder;
use Modules\Resto\Domain\Enums\OrderChannel;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\Outlet;
use Tests\TestCase;

class CrossLineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $cashier;

    protected User $tenantOwner;

    protected Property $property;

    protected Tenant $restoTenant;

    protected Outlet $restoOutlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(MallSeeder::class);
        $this->seed(RestoMenuSeeder::class);

        app(MallLedgerAccounts::class)->ensureAll();

        $this->property = Property::where('code', 'DM-BJM')->firstOrFail();
        $this->restoTenant = Tenant::where('external_ref', 'DM-01')->firstOrFail();
        $this->restoOutlet = Outlet::where('code', 'DM-01')->firstOrFail();

        // Customer setup with balance and wallet PIN
        $this->customer = User::firstOrCreate(
            ['email' => 'customer.crossline@dutamall.test'],
            ['name' => 'Budi Crossline', 'role' => 'customer', 'password' => bcrypt('password')]
        );
        $this->customer->walletAccount('IDR');
        app(TopUpAction::class)->execute($this->customer, 1_000_000, 'customer-seed-topup');
        app(SetPinAction::class)->execute($this->customer, '123456');

        // Cashier setup
        $this->cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();

        // Tenant owner setup with balance and wallet PIN
        $this->tenantOwner = User::firstOrCreate(
            ['email' => 'owner.sariranah@dutamall.test'],
            ['name' => 'Owner Sari Ranah', 'role' => 'admin', 'password' => bcrypt('password')]
        );
        $this->restoTenant->update(['user_id' => $this->tenantOwner->id]);
        $this->tenantOwner->walletAccount('IDR');
        for ($i = 1; $i <= 4; $i++) {
            app(TopUpAction::class)->execute($this->tenantOwner, 50_000_000, "tenant-seed-topup-{$i}");
        }
        app(SetPinAction::class)->execute($this->tenantOwner, '123456');
    }

    public function test_full_cross_line_lifecycle(): void
    {
        // -------------------------------------------------------------------------
        // 1. Kendaraan masuk mall -> dapat tiket parkir
        // -------------------------------------------------------------------------
        $plateNumber = 'DA 8888 XY';
        $parkingSession = app(CheckInVehicleAction::class)->execute(
            property: $this->property,
            plateNumber: $plateNumber,
            vehicleType: VehicleType::CAR,
            entryGate: 'Gate Utama'
        );

        $this->assertInstanceOf(ParkingSession::class, $parkingSession);
        $this->assertSame(ParkingSessionStatus::ACTIVE, $parkingSession->status);
        $this->assertTrue(str_starts_with($parkingSession->ticket_number, 'TKT-'));
        $this->assertNull($parkingSession->validated_by_tenant_id);

        // -------------------------------------------------------------------------
        // 2. Pelanggan makan di RM Sari Ranah (outlet DM-01)
        // -------------------------------------------------------------------------
        $shift = app(OpenShiftAction::class)->handle(
            cashierId: $this->cashier->id,
            outletId: $this->restoOutlet->id,
            openingFloat: 200_000
        );

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'outlet_id' => $this->restoOutlet->id,
            'number' => 'ORD-CROSS-001',
            'channel' => OrderChannel::DINE_IN,
            'customer_id' => $this->customer->id,
            'guest_name' => $this->customer->name,
            'subtotal' => 0,
            'discount' => 0,
            'service_charge' => 0,
            'tax_pb1' => 0,
            'rounding' => 0,
            'grand_total' => 0,
            'status' => OrderStatus::OPEN,
            'shift_id' => $shift->id,
            'idempotency_key' => 'idem-order-cross-'.Str::random(8),
        ]);

        $menuItem = MenuItem::firstOrFail();
        app(AddOrderItemAction::class)->handle(
            order: $order,
            menuItemId: $menuItem->id,
            qty: 2,
            priceOverride: 75_000
        );

        $order->subtotal = 150_000;
        $order->grand_total = 150_000;
        $order->status = OrderStatus::AWAITING_PAYMENT;
        $order->save();

        // -------------------------------------------------------------------------
        // 3. Pelanggan tukar voucher mall & bayar pesanan dengan Wallet (+PIN)
        // -------------------------------------------------------------------------
        // Berikan poin terlebih dahulu lalu tukar voucher
        app(ClaimReceiptPointsAction::class)->execute(
            user: $this->customer,
            receiptNumber: 'INITIAL-RECEIPT-EARN',
            receiptAmount: 500_000,
            receiptDate: Carbon::today(),
            tenantId: $this->restoTenant->id
        );
        $this->assertGreaterThan(0, $this->customer->pointsBalance());

        $voucherTemplate = VoucherTemplate::where('points_required', '<=', $this->customer->pointsBalance())->firstOrFail();
        $voucher = app(RedeemVoucherAction::class)->execute($this->customer, $voucherTemplate);

        $initialWalletBalance = (int) $this->customer->walletBalance('IDR')->amount->toInt();

        $paidOrder = app(PayOrderAction::class)->handle(
            order: $order,
            paymentMethod: 'wallet',
            pin: '123456',
            shiftId: $shift->id,
            cashierUserId: $this->cashier->id,
            payerUser: $this->customer,
            parkingTicketNumber: $parkingSession->ticket_number,
            mallVoucherCode: $voucher->voucher_code
        );

        $this->assertSame(OrderStatus::PAID, $paidOrder->status);
        $this->assertSame($voucher->voucher_code, $paidOrder->mall_voucher_code);
        $this->assertGreaterThan(0, $paidOrder->mall_voucher_discount);
        $this->assertLessThan(150_000, $paidOrder->grand_total);
        $this->assertLessThan($initialWalletBalance, (int) $this->customer->walletBalance('IDR')->amount->toInt());

        // -------------------------------------------------------------------------
        // 4. Pesanan earn Duta Points (PTS bertambah seimbang di ledger)
        // -------------------------------------------------------------------------
        $this->assertGreaterThan(0, $paidOrder->loyalty_points_earned);
        $this->assertSame(0, Artisan::call('bank:reconcile'), 'Buku besar harus balance setelah earn points & pembayaran wallet.');

        // -------------------------------------------------------------------------
        // 5. Kasir validasi tiket parkir pelanggan -> diskon jam parkir tercatat
        // -------------------------------------------------------------------------
        $parkingSession->refresh();
        $this->assertSame($this->restoTenant->id, $parkingSession->validated_by_tenant_id);
        $this->assertGreaterThan(0, $parkingSession->validation_free_hours);
        $this->assertSame($parkingSession->ticket_number, $paidOrder->parking_ticket_number);

        // -------------------------------------------------------------------------
        // 6. Kendaraan keluar gate -> tarif terpotong validasi tenant
        // -------------------------------------------------------------------------
        // Berada di dalam mall selama 90 menit (dalam batas gratis validasi)
        $exitTime = $parkingSession->entry_time->copy()->addMinutes(90);
        $checkedOutSession = app(CheckOutVehicleAction::class)->execute(
            session: $parkingSession,
            exitGate: 'Gate Keluar 1',
            exitTime: $exitTime
        );

        $this->assertSame(ParkingSessionStatus::COMPLETED, $checkedOutSession->status);
        $this->assertGreaterThan(0, $checkedOutSession->discount_amount);
        $this->assertSame(0, $checkedOutSession->total_fee);
        $this->assertSame(ParkingPaymentMethod::TENANT_FREE, $checkedOutSession->payment_method);

        // -------------------------------------------------------------------------
        // 7. Kendaraan transfer kepemilikan di AutoDex -> member parkir lama otomatis nonaktif
        // -------------------------------------------------------------------------
        $brand = Brand::firstOrCreate(
            ['name' => 'Honda'],
            ['slug' => 'honda', 'country' => 'Japan', 'category' => 'jdm']
        );

        $car = Car::first() ?? Car::create([
            'brand_id' => $brand->id,
            'model' => 'Civic Turbo',
            'slug' => 'honda-civic-turbo-2022',
            'year_start' => 2022,
            'price_idr' => 450_000_000,
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'user_id' => $this->customer->id,
            'car_id' => $car->id,
            'plate_number' => 'DA 9999 ZZ',
            'vin' => 'VIN-'.Str::random(12),
            'status' => 'active',
        ]);

        $member = app(RegisterParkingMemberAction::class)->execute(
            property: $this->property,
            user: $this->customer,
            vehicle: $vehicle,
            pin: '123456',
            months: 1
        );
        $this->assertSame(MemberStatus::ACTIVE, $member->status);

        $newBuyer = User::create([
            'name' => 'Buyer Baru',
            'email' => 'buyer.crossline@dutamall.test',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        app(TransferVehicleOwnershipAction::class)->handle(
            vehicle: $vehicle,
            toUserId: $newBuyer->id,
            viaType: 'autodex',
            priceIdr: 450_000_000
        );

        $this->assertSame(MemberStatus::CANCELLED, $member->fresh()->status);

        // -------------------------------------------------------------------------
        // 8. Akhir bulan: jalankan mall:generate-invoices
        //    Invoice sewa RM Sari Ranah otomatis menarik omzet via RestoTenantSalesProvider
        //    dan menagihkan baris parking_validation
        // -------------------------------------------------------------------------
        $currentMonth = Carbon::now()->format('Y-m');
        $lease = Lease::where('tenant_id', $this->restoTenant->id)->firstOrFail();
        Invoice::where('lease_id', $lease->id)->where('period_month', $currentMonth)->delete();

        $exitGen = Artisan::call('mall:generate-invoices', ['--month' => $currentMonth]);
        $this->assertSame(0, $exitGen);

        $invoice = Invoice::where('lease_id', $lease->id)
            ->where('period_month', $currentMonth)
            ->firstOrFail();

        $this->assertSame(InvoiceStatus::ISSUED, $invoice->status);

        // Cek bahwa baris parking_validation ditagihkan sesuai diskon tiket pelanggan
        $validationLine = $invoice->lines()->where('type', InvoiceLineType::PARKING_VALIDATION)->first();
        $this->assertNotNull($validationLine, 'Baris validasi parkir harus ada di invoice tenant.');
        $this->assertSame($checkedOutSession->discount_amount, (int) $validationLine->amount);

        // Cek bahwa sesi parkir telah ditautkan ke invoice
        $this->assertSame($invoice->id, $checkedOutSession->fresh()->validation_invoice_id);

        // -------------------------------------------------------------------------
        // 9. Tenant bayar invoice sewa
        // -------------------------------------------------------------------------
        $paidInvoice = app(PayInvoiceAction::class)->execute(
            invoice: $invoice,
            amount: $invoice->total_amount,
            pin: '123456',
            user: $this->tenantOwner
        );

        $this->assertSame(InvoiceStatus::PAID, $paidInvoice->status);
        $this->assertSame($invoice->total_amount, $paidInvoice->paid_amount);

        // -------------------------------------------------------------------------
        // 10. Audit checks: bank:reconcile & mall:audit-billing lolos bersih
        // -------------------------------------------------------------------------
        $exitReconcile = Artisan::call('bank:reconcile');
        $this->assertSame(0, $exitReconcile, 'Rekonsiliasi double-entry ledger harus 0 selisih.');

        $exitAuditBilling = Artisan::call('mall:audit-billing');
        $this->assertSame(0, $exitAuditBilling, 'Audit billing mall harus lolos.');
    }

    public function test_global_search_endpoint_returns_cross_module_results(): void
    {
        // 1. Tanpa query: default suggestions (quick actions)
        $resDefault = $this->actingAs($this->customer)->getJson(route('api.global-search'));
        $resDefault->assertOk();
        $resDefault->assertJsonStructure(['results']);
        $this->assertNotEmpty($resDefault->json('results'));

        // 2. Pencarian kata kunci lintas modul: 'Sari'
        $resSari = $this->actingAs($this->customer)->getJson(route('api.global-search', ['q' => 'Sari']));
        $resSari->assertOk();
        $results = $resSari->json('results');
        $this->assertNotEmpty($results);

        $titles = array_column($results, 'title');
        $matched = false;
        foreach ($titles as $title) {
            if (stripos($title, 'Sari') !== false) {
                $matched = true;
                break;
            }
        }
        $this->assertTrue($matched, 'Pencarian harus menemukan data Sari Ranah.');
    }

    public function test_consolidated_group_dashboard_query_budget_and_response(): void
    {
        // Hanya admin yang dapat mengakses
        $admin = User::firstOrCreate(
            ['email' => 'admin.holding@autoserve.test'],
            ['name' => 'Direktur Utama', 'role' => 'admin', 'password' => bcrypt('password')]
        );

        // Budget query <= 30
        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get(route('admin.group-dashboard'));
        $response->assertOk();
        $response->assertSee('Dashboard Grup Konsolidasi');
        $response->assertSee('Konsolidasi Holding 4 Pilar Usaha');
        $response->assertSee('Kuliner');
        $response->assertSee('Properti');

        $queryCount = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(30, $queryCount, "Dashboard konsolidasi melebihi budget query (terekam: {$queryCount})");
    }
}
