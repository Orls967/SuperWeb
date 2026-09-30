<?php

declare(strict_types=1);

namespace Modules\Resto\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Resto\Application\Actions\CancelCateringAction;
use Modules\Resto\Application\Actions\CreateCateringOrderAction;
use Modules\Resto\Application\Actions\CreateDeliveryOrderAction;
use Modules\Resto\Application\Actions\DeliverAndCompleteCateringAction;
use Modules\Resto\Application\Actions\FailDeliveryAction;
use Modules\Resto\Application\Actions\HoldCateringDepositAction;
use Modules\Resto\Application\Queries\MenuEngineeringQuery;
use Modules\Resto\database\seeders\RestoMenuSeeder;
use Modules\Resto\Domain\Enums\CateringStatus;
use Modules\Resto\Domain\Enums\DeliveryStatus;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Exceptions\CateringCapacityExceededException;
use Modules\Resto\Domain\Models\CateringPackage;
use Modules\Resto\Domain\Models\DailySummary;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\OrderItem;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\OutletContract;
use Modules\Resto\Domain\Models\RoyaltyPosting;
use Tests\TestCase;

class RestoSalesChannelsAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected Outlet $outlet;

    protected Ingredient $packagingIngredient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([BankingSeeder::class, RestoMenuSeeder::class]);

        $this->customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Budi Santoso',
            'phone' => '081299998888',
        ]);

        $this->outlet = Outlet::where('code', 'DM-01')->firstOrFail();

        // Pastikan bahan baku kemasan tersedia
        $this->packagingIngredient = Ingredient::firstOrCreate(
            ['sku' => 'ING-KERTAS-BUNGKUS'],
            [
                'name' => 'Kertas Nasi Bungkus Coklat Laminasi',
                'category' => 'kemasan',
                'base_unit' => 'lembar',
                'cost_per_unit' => 250,
                'min_stock' => 100,
                'is_active' => true,
            ]
        );

        // Berikan stok kemasan 100 lembar ke outlet
        app(InventoryService::class)->addIngredient(
            ingredientId: $this->packagingIngredient->id,
            outletId: $this->outlet->id,
            qtyBaseUnit: '100',
            reason: StockMovementReason::PURCHASE,
            sourceType: 'initial_stock',
            sourceId: 1
        );
    }

    public function test_create_delivery_order_uses_takeaway_price_and_deducts_packaging_stock(): void
    {
        // Top up saldo customer Rp500.000
        app(TopUpAction::class)->execute($this->customer, '500000', 'topup_dlv_test');

        $menuItem = MenuItem::where('sku', 'MNU-RENDANG-DAGING')->firstOrFail();
        $menuItem->update([
            'base_price' => 25000,
            'takeaway_price' => 27000, // Harga khusus bungkus lebih tinggi Rp2.000
        ]);

        $initialPackagingStock = (float) app(InventoryService::class)->availableIngredient(
            $this->packagingIngredient->id,
            $this->outlet->id
        );

        $action = app(CreateDeliveryOrderAction::class);
        $result = $action->handle(
            outletId: $this->outlet->id,
            itemsData: [
                ['menu_item_id' => $menuItem->id, 'qty' => 2],
            ],
            recipientName: 'Budi Santoso',
            recipientPhone: '081299998888',
            deliveryAddress: 'Jl. Ahmad Yani No. 45, Banjarmasin',
            distanceKm: 4.5, // > 3 km -> ada biaya jarak ekstra
            customerUser: $this->customer,
            paymentMethod: 'wallet'
        );

        $order = $result['order'];
        $delivery = $result['delivery'];

        // Subtotal makanan: 2 x 27.000 = 54.000
        $this->assertEquals(54000, $order->subtotal);

        // Biaya jarak 4.5 km: 10.000 + ceil(4.5 - 3.0) * 2.500 = 10.000 + 2 * 2.500 = 15.000
        $this->assertEquals(15000, $delivery->delivery_fee);

        // Biaya kemasan: 2 porsi x 2.000 = 4.000
        $this->assertEquals(4000, $delivery->packaging_fee);

        // Pajak PB1 10% atas makanan: 10% x 54.000 = 5.400
        $this->assertEquals(5400, $order->tax_pb1);

        // Status Order PAID & Status Delivery PREPARING
        $this->assertEquals(OrderStatus::PAID, $order->status);
        $this->assertEquals(DeliveryStatus::PREPARING, $delivery->status);

        // Stok kertas bungkus berkurang 2 lembar
        $newPackagingStock = (float) app(InventoryService::class)->availableIngredient(
            $this->packagingIngredient->id,
            $this->outlet->id
        );
        $this->assertEquals($initialPackagingStock - 2, $newPackagingStock);
    }

    public function test_failed_delivery_refunds_food_and_pb1_but_retains_delivery_fee(): void
    {
        // Top up customer Rp300.000
        app(TopUpAction::class)->execute($this->customer, '300000', 'topup_fail_dlv');

        $menuItem = MenuItem::first();
        $menuItem->update(['takeaway_price' => 20000]);

        $action = app(CreateDeliveryOrderAction::class);
        $result = $action->handle(
            outletId: $this->outlet->id,
            itemsData: [
                ['menu_item_id' => $menuItem->id, 'qty' => 1],
            ],
            recipientName: 'Budi Santoso',
            recipientPhone: '081299998888',
            deliveryAddress: 'Komplek Kurir Nyasar',
            distanceKm: 2.0, // Ongkir dasar Rp10.000
            customerUser: $this->customer,
            paymentMethod: 'wallet'
        );

        $order = $result['order'];
        $delivery = $result['delivery'];

        $customerWalletAcc = $this->customer->walletAccount('IDR');
        $balanceAfterOrder = (int) $customerWalletAcc->cached_balance;

        // Gagal kirim: kurir tidak menemukan alamat setelah 3x percobaan
        $failAction = app(FailDeliveryAction::class);
        $updatedDelivery = $failAction->handle(
            delivery: $delivery,
            failureReason: 'Alamat tidak ditemukan / penerima tidak merespons',
            refundFoodOnly: true
        );

        $this->assertEquals(DeliveryStatus::FAILED, $updatedDelivery->status);
        $this->assertEquals(OrderStatus::REFUNDED, $order->fresh()->status);

        // Verifikasi refund: Saldo customer bertambah sebesar (subtotal + PB1 + biaya kemasan), ongkir Rp10.000 ditahan
        $customerWalletAcc->refresh();
        $balanceAfterRefund = (int) $customerWalletAcc->cached_balance;

        $foodShare = $order->subtotal + $order->tax_pb1 + $order->service_charge + $order->rounding;
        $this->assertEquals($balanceAfterOrder + $foodShare, $balanceAfterRefund);
    }

    public function test_catering_capacity_check_rejects_order_exceeding_daily_capacity(): void
    {
        $createCatering = app(CreateCateringOrderAction::class);
        $eventDate = Carbon::now()->addDays(5)->toDateString();

        // 1. Buat pesanan katering 450 pax (kapasitas outlet reguler = 500 pax/hari)
        $order1 = $createCatering->handle(
            outletId: $this->outlet->id,
            customerName: 'Keluarga Hasanah',
            customerPhone: '0811223344',
            deliveryAddress: 'Gedung Serbaguna Banjarmasin',
            eventDate: $eventDate,
            pax: 450
        );
        $this->assertNotNull($order1->id);

        // 2. Coba buat pesanan katering kedua di tanggal yang sama sebesar 100 pax (450 + 100 = 550 > 500)
        $this->expectException(CateringCapacityExceededException::class);

        $createCatering->handle(
            outletId: $this->outlet->id,
            customerName: 'Akad Nikah Ridwan',
            customerPhone: '0811998877',
            deliveryAddress: 'Masjid Raya Sabilal',
            eventDate: $eventDate,
            pax: 100
        );
    }

    public function test_catering_stepped_payment_escrow_hold_and_complete_charge(): void
    {
        // Top up customer Rp5.000.000
        app(TopUpAction::class)->execute($this->customer, '5000000', 'topup_catering_pax');

        $package = CateringPackage::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Paket Prasmanan Padang Istimewa',
            'slug' => 'paket-prasmanan-padang',
            'min_pax' => 50,
            'price_per_pax' => 40000,
            'description' => 'Rendang, Ayam Pop, Gulai Tunjang, Sambal Ijo, Sayur Kapau',
            'is_active' => true,
        ]);

        $eventDate = Carbon::now()->addDays(7)->toDateString();
        $createCatering = app(CreateCateringOrderAction::class);

        $order = $createCatering->handle(
            outletId: $this->outlet->id,
            customerName: 'H. Syamsudin',
            customerPhone: '081234567890',
            deliveryAddress: 'Ballroom Hotel Duta',
            eventDate: $eventDate,
            pax: 50,
            packageId: $package->id,
            user: $this->customer
        );

        // Total = 50 pax * 40.000 + 50.000 delivery = 2.050.000
        $this->assertEquals(2050000, $order->grand_total);
        // Deposit 30% = 615.000
        $this->assertEquals(615000, $order->deposit_amount);
        $this->assertEquals(CateringStatus::QUOTED, $order->status);

        // Step 1: Hold deposit 30% in escrow
        $holdAction = app(HoldCateringDepositAction::class);
        $order = $holdAction->handle($order, $this->customer);

        $this->assertEquals(CateringStatus::CONFIRMED, $order->status);
        $this->assertNotNull($order->deposit_intent_id);

        $walletAcc = $this->customer->walletAccount('IDR');
        $this->assertEquals(5000000 - 615000, (int) $walletAcc->cached_balance);

        // Step 2: Selesaikan pesanan di hari H (Capture deposit 30% + Potong sisa 70% dari dompet)
        $completeAction = app(DeliverAndCompleteCateringAction::class);
        $completedOrder = $completeAction->handle($order);

        $this->assertEquals(CateringStatus::COMPLETED, $completedOrder->status);
        $this->assertEquals($order->grand_total, $completedOrder->paid_amount);

        // Sisa 70% = 2.050.000 - 615.000 = 1.435.000
        $walletAcc->refresh();
        $this->assertEquals(5000000 - 2050000, (int) $walletAcc->cached_balance);
    }

    public function test_catering_cancellation_refunds_deposit_if_h_minus_3_or_more(): void
    {
        app(TopUpAction::class)->execute($this->customer, '2000000', 'topup_catering_cancel_early');

        $eventDate = Carbon::now()->addDays(5)->toDateString(); // H-5
        $order = app(CreateCateringOrderAction::class)->handle(
            outletId: $this->outlet->id,
            customerName: 'Ibu Rahmawati',
            customerPhone: '0812334455',
            deliveryAddress: 'Rumah Duka / Syukuran',
            eventDate: $eventDate,
            pax: 20,
            user: $this->customer
        );

        $order = app(HoldCateringDepositAction::class)->handle($order, $this->customer);

        $walletAcc = $this->customer->walletAccount('IDR');
        $balanceWhileHeld = (int) $walletAcc->cached_balance;

        // Batalkan pada H-5 (>= 3 hari sebelum acara)
        $cancelAction = app(CancelCateringAction::class);
        $cancelledOrder = $cancelAction->handle($order, 'Perubahan jadwal rapat kantor');

        $this->assertEquals(CateringStatus::CANCELLED, $cancelledOrder->status);

        // Deposit hold dilepas kembali ke saldo customer
        $walletAcc->refresh();
        $this->assertEquals(2000000, (int) $walletAcc->cached_balance);
    }

    public function test_catering_cancellation_forfeits_deposit_if_less_than_3_days(): void
    {
        app(TopUpAction::class)->execute($this->customer, '2000000', 'topup_catering_cancel_late');

        $eventDate = Carbon::now()->addDays(1)->toDateString(); // H-1
        $order = app(CreateCateringOrderAction::class)->handle(
            outletId: $this->outlet->id,
            customerName: 'Bapak Arifin',
            customerPhone: '0812334456',
            deliveryAddress: 'Kantor Bappeda',
            eventDate: $eventDate,
            pax: 20,
            user: $this->customer
        );

        $order = app(HoldCateringDepositAction::class)->handle($order, $this->customer);

        $walletAcc = $this->customer->walletAccount('IDR');
        $balanceWhileHeld = (int) $walletAcc->cached_balance;

        // Batalkan mendadak H-1 (< 3 hari sebelum acara)
        $cancelAction = app(CancelCateringAction::class);
        $cancelledOrder = $cancelAction->handle($order, 'Pembatalan mendadak oleh panitia');

        $this->assertEquals(CateringStatus::CANCELLED, $cancelledOrder->status);

        // Deposit hangus (tidak dikembalikan ke wallet customer)
        $walletAcc->refresh();
        $this->assertEquals($balanceWhileHeld, (int) $walletAcc->cached_balance);
    }

    public function test_post_franchise_royalty_command_calculates_and_posts_to_holding_revenue(): void
    {
        // 1. Buat Kontrak Franchise untuk outlet DM-01 (Royalti 5%, Marketing 2%)
        $contract = OutletContract::create([
            'uuid' => (string) Str::uuid(),
            'outlet_id' => $this->outlet->id,
            'franchisee_user_id' => $this->customer->id,
            'contract_number' => 'CTR-FRN-TEST-001',
            'royalty_percent' => '5.00',
            'marketing_fee_percent' => '2.00',
            'fixed_monthly_management_fee' => 0,
            'valid_from' => now()->subMonths(1)->toDateString(),
            'valid_until' => now()->addYears(2)->toDateString(),
            'is_active' => true,
        ]);

        $yesterday = Carbon::yesterday()->toDateString();

        // 2. Simulasikan ringkasan penjualan harian (DailySummary)
        DailySummary::create([
            'outlet_id' => $this->outlet->id,
            'date' => $yesterday,
            'total_orders' => 120,
            'gross_sales' => 11000000,
            'discount_amount' => 0,
            'tax_amount' => 1000000,
            'net_sales' => 10000000, // Rp10.000.000
            'cogs_amount' => 4000000,
            'waste_value' => 50000,
            'is_closed' => true,
        ]);

        // 3. Jalankan command resto:post-royalty
        $exitCode = Artisan::call('resto:post-royalty', [
            '--date' => $yesterday,
            '--outlet' => $this->outlet->id,
        ]);
        $this->assertEquals(0, $exitCode);

        // 4. Verifikasi RoyaltyPosting record
        $posting = RoyaltyPosting::where('outlet_id', $this->outlet->id)
            ->whereDate('date', $yesterday)
            ->firstOrFail();

        $this->assertEquals(10000000, $posting->net_sales);
        $this->assertEquals(500000, $posting->royalty_amount); // 5% dari 10jt = 500rb
        $this->assertEquals(200000, $posting->marketing_fee_amount); // 2% dari 10jt = 200rb
        $this->assertEquals(9300000, $posting->franchisee_net_share);
        $this->assertNotNull($posting->ledger_transaction_id);

        // 5. Verifikasi saldo akun pendapatan holding grup di buku besar
        $royaltyRevAcc = LedgerAccount::where('code', 'revenue:group:royalty:IDR')->firstOrFail();
        $marketingRevAcc = LedgerAccount::where('code', 'revenue:group:marketing:IDR')->firstOrFail();
        $expAcc = LedgerAccount::where('code', 'expense:resto:franchise_royalty:IDR')->firstOrFail();

        $this->assertEquals(500000, (int) $royaltyRevAcc->cached_balance);
        $this->assertEquals(200000, (int) $marketingRevAcc->cached_balance);
        $this->assertEquals(-700000, (int) $expAcc->cached_balance);

        // 6. Jalankan kembali command untuk tanggal yang sama (idempoten - tidak double post)
        $exitCode2 = Artisan::call('resto:post-royalty', [
            '--date' => $yesterday,
            '--outlet' => $this->outlet->id,
        ]);
        $this->assertEquals(0, $exitCode2);
        $this->assertEquals(1, RoyaltyPosting::where('outlet_id', $this->outlet->id)->whereDate('date', $yesterday)->count());
    }

    public function test_menu_engineering_query_classifies_bcg_matrix_quadrants(): void
    {
        $items = MenuItem::where('is_active', true)->limit(4)->get();
        $this->assertGreaterThanOrEqual(2, $items->count());

        // Buat order buatan untuk memberi volume penjualan
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'outlet_id' => $this->outlet->id,
            'number' => 'ORD-BCG-TEST',
            'status' => OrderStatus::PAID,
            'grand_total' => 200000,
            'payment_method' => 'cash',
            'idempotency_key' => 'idem_bcg_test_'.Str::uuid(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $items[0]->id,
            'qty' => 50,
            'unit_price_snapshot' => $items[0]->price,
            'line_total' => $items[0]->price * 50,
            'name_snapshot' => $items[0]->name,
            'consumed_state' => 'consumed',
        ]);

        $query = app(MenuEngineeringQuery::class);
        $result = $query->execute($this->outlet->id, 30);

        $this->assertArrayHasKey('average_volume', $result);
        $this->assertArrayHasKey('average_margin', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('quadrant_counts', $result);

        $counts = $result['quadrant_counts'];
        $this->assertArrayHasKey('star', $counts);
        $this->assertArrayHasKey('plowhorse', $counts);
        $this->assertArrayHasKey('puzzle', $counts);
        $this->assertArrayHasKey('dog', $counts);
    }

    public function test_reconcile_remains_balanced_after_all_transactions(): void
    {
        $exitCode = Artisan::call('bank:reconcile');
        $this->assertEquals(0, $exitCode);
    }
}
