<?php

declare(strict_types=1);

namespace Modules\Resto\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Resto\Application\Actions\AddOrderItemAction;
use Modules\Resto\Application\Actions\CalculateHidangBillAction;
use Modules\Resto\Application\Actions\CloseShiftAction;
use Modules\Resto\Application\Actions\CookBatchAction;
use Modules\Resto\Application\Actions\OpenShiftAction;
use Modules\Resto\Application\Actions\OpenTableSessionAction;
use Modules\Resto\Application\Actions\PayOrderAction;
use Modules\Resto\Application\Actions\PresentHidangAction;
use Modules\Resto\Application\Actions\VoidOrderAction;
use Modules\Resto\database\seeders\RestoMenuSeeder;
use Modules\Resto\Domain\Enums\BatchStatus;
use Modules\Resto\Domain\Enums\ConsumedState;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Enums\SessionStatus;
use Modules\Resto\Domain\Enums\ShiftStatus;
use Modules\Resto\Domain\Enums\TableStatus;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Exceptions\InvalidOrderOperationException;
use Modules\Resto\Domain\Exceptions\TableOccupiedException;
use Modules\Resto\Domain\Models\DailySummary;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\ProductionBatch;
use Modules\Resto\Domain\Models\Recipe;
use Modules\Resto\Domain\Models\RestoTable;
use Modules\Resto\Domain\Models\Shift;
use Tests\TestCase;

class RestoPosAndShiftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RestoMenuSeeder::class);
    }

    public function test_alur_hidang_lengkap_12_piring_dihidang_5_disentuh_hanya_5_dibayar_7_kembali_ke_etalase(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();
        $table = RestoTable::where('outlet_id', $outlet->id)->where('code', 'A1')->firstOrFail();

        // 1. Open shift
        $openShiftAction = app(OpenShiftAction::class);
        $shift = $openShiftAction->handle(
            cashierId: $cashier->id,
            outletId: $outlet->id,
            openingFloat: 500_000
        );
        $this->assertEquals(ShiftStatus::OPEN, $shift->status);

        // 2. Open table session
        $openSessionAction = app(OpenTableSessionAction::class);
        $session = $openSessionAction->handle(
            tableId: $table->id,
            guestCount: 4,
            openedBy: $cashier->id
        );
        $this->assertEquals(SessionStatus::OPEN, $session->status);
        $this->assertEquals(TableStatus::OCCUPIED, $table->fresh()->status);

        // 3. Prepare 12 display trays
        $recipes = Recipe::whereNotNull('menu_item_id')->whereHas('lines')->take(12)->get();
        $cookAction = app(CookBatchAction::class);
        $trayIds = [];

        foreach ($recipes as $rec) {
            $batch = $cookAction->handle(
                outletId: $outlet->id,
                recipeId: $rec->id,
                plannedPortions: 5,
                actualPortions: 5,
                putOnDisplay: true
            );
            $tray = $batch->trays()->first();
            if ($tray) {
                $trayIds[] = $tray->id;
            }
        }

        // Fill up to 12 if fewer recipes were available
        while (count($trayIds) < 12) {
            $firstMenuItem = MenuItem::first();
            $dummyBatch = ProductionBatch::create([
                'uuid' => (string) Str::uuid(),
                'outlet_id' => $outlet->id,
                'recipe_id' => $recipes->first()?->id ?? 1,
                'batch_no' => 'BATCH-TEST-'.Str::random(6),
                'planned_portions' => 10,
                'actual_portions' => 10,
                'cooked_at' => now(),
                'expires_at' => now()->addHours(6),
                'status' => BatchStatus::ON_DISPLAY,
                'cost_total' => 100_000,
                'cost_per_portion' => 10_000,
            ]);
            $dummyTray = DisplayTray::create([
                'uuid' => (string) Str::uuid(),
                'outlet_id' => $outlet->id,
                'batch_id' => $dummyBatch->id,
                'menu_item_id' => $firstMenuItem->id,
                'portions_remaining' => 5,
                'recirculation_count' => 0,
                'placed_at' => now(),
                'expires_at' => now()->addHours(6),
                'status' => TrayStatus::ON_DISPLAY,
                'cost_per_portion' => 10_000,
            ]);
            $trayIds[] = $dummyTray->id;
        }

        $trayIds12 = array_slice($trayIds, 0, 12);
        $this->assertCount(12, $trayIds12);

        // 4. Present 12 trays to table
        $presentAction = app(PresentHidangAction::class);
        $order = $presentAction->handle($session, $trayIds12);
        $this->assertCount(12, $order->items);
        $this->assertEquals(0, $order->subtotal); // Initially 0 subtotal

        // 5. Customer eats: 5 plates consumed, 7 returned untouched
        $calcBillAction = app(CalculateHidangBillAction::class);
        $consumedMap = [];
        $consumedItemIds = [];
        $returnedTrayIds = [];

        foreach ($order->items as $idx => $item) {
            if ($idx < 5) {
                $consumedMap[$item->id] = true;
                $consumedItemIds[] = $item->id;
            } else {
                $consumedMap[$item->id] = false;
                $returnedTrayIds[] = $item->tray_id;
            }
        }

        $order = $calcBillAction->handle(
            session: $session,
            consumedStatuses: $consumedMap,
            discount: 0,
            userId: $cashier->id
        );

        $this->assertEquals(OrderStatus::AWAITING_PAYMENT, $order->status);
        $this->assertGreaterThan(0, $order->subtotal);

        // Assert 5 items are consumed
        $consumedItems = $order->items()->where('consumed_state', ConsumedState::CONSUMED)->get();
        $this->assertCount(5, $consumedItems);

        // Assert 7 items are returned
        $returnedItems = $order->items()->where('consumed_state', ConsumedState::RETURNED)->get();
        $this->assertCount(7, $returnedItems);

        // Assert the 7 trays were returned to etalase with recirculation_count = 1
        foreach ($returnedTrayIds as $tId) {
            $t = DisplayTray::find($tId);
            $this->assertEquals(1, $t->recirculation_count);
            $this->assertEquals(TrayStatus::ON_DISPLAY, $t->status);
        }

        // 6. Pay order via cash
        $payAction = app(PayOrderAction::class);
        $tendered = $order->grand_total + 50_000;
        $paidOrder = $payAction->handle(
            order: $order,
            paymentMethod: 'cash',
            cashTendered: $tendered,
            shiftId: $shift->id,
            cashierUserId: $cashier->id
        );

        $this->assertEquals(OrderStatus::PAID, $paidOrder->status);
        $this->assertEquals(TableStatus::AVAILABLE, $table->fresh()->status);
        $this->assertEquals(SessionStatus::CLOSED, $session->fresh()->status);

        // Verify reconcile
        $this->artisan('bank:reconcile')->assertExitCode(0);
    }

    public function test_piring_disentuh_sebagian_dihitung_terjual_penuh(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();
        $table = RestoTable::where('outlet_id', $outlet->id)->where('code', 'A2')->firstOrFail();

        $session = app(OpenTableSessionAction::class)->handle(
            tableId: $table->id,
            guestCount: 2,
            openedBy: $cashier->id
        );

        $menuItem = MenuItem::where('name', 'like', '%Rendang%')->firstOrFail();
        $batch = ProductionBatch::create([
            'uuid' => (string) Str::uuid(),
            'outlet_id' => $outlet->id,
            'recipe_id' => Recipe::first()?->id ?? 1,
            'batch_no' => 'BATCH-TEST-'.Str::random(6),
            'planned_portions' => 10,
            'actual_portions' => 10,
            'cooked_at' => now(),
            'expires_at' => now()->addHours(6),
            'status' => BatchStatus::ON_DISPLAY,
            'cost_total' => 150_000,
            'cost_per_portion' => 15_000,
        ]);
        $tray = DisplayTray::create([
            'uuid' => (string) Str::uuid(),
            'outlet_id' => $outlet->id,
            'batch_id' => $batch->id,
            'menu_item_id' => $menuItem->id,
            'portions_remaining' => 10,
            'recirculation_count' => 0,
            'placed_at' => now(),
            'expires_at' => now()->addHours(6),
            'status' => TrayStatus::ON_DISPLAY,
            'cost_per_portion' => 15_000,
        ]);

        $order = app(PresentHidangAction::class)->handle($session, [$tray->id]);
        $orderItem = $order->items->first();

        // Customer ate partially -> marked as consumed (full plate price)
        $order = app(CalculateHidangBillAction::class)->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'consumed']
        );

        $this->assertEquals($menuItem->base_price, $order->subtotal);
        $this->assertEquals($menuItem->base_price, $orderItem->fresh()->line_total);
        $this->assertEquals(ConsumedState::CONSUMED, $orderItem->fresh()->consumed_state);
        // Tray remaining portions decreased
        $this->assertEquals(9, $tray->fresh()->portions_remaining);
    }

    public function test_pb1_dan_pembulatan_tepat_ke_100_terdekat(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();
        $table = RestoTable::where('outlet_id', $outlet->id)->where('code', 'A3')->firstOrFail();

        $session = app(OpenTableSessionAction::class)->handle($table->id, 2, $cashier->id);
        $order = $session->orders->first();

        // Add item with price that creates odd decimals after PB1: e.g., Rp 45.750
        $menuItem = MenuItem::first();
        app(AddOrderItemAction::class)->handle($order, $menuItem->id, 1, 45_750);

        $calculatedOrder = app(CalculateHidangBillAction::class)->handle($session);

        $subtotal = 45_750;
        $expectedPb1 = (int) round($subtotal * 0.10); // 4575
        $rawTotal = $subtotal + $expectedPb1; // 50325
        $expectedGrandTotal = (int) (round($rawTotal / 100) * 100); // 50300
        $expectedRounding = $expectedGrandTotal - $rawTotal; // -25

        $this->assertEquals($subtotal, $calculatedOrder->subtotal);
        $this->assertEquals($expectedPb1, $calculatedOrder->tax_pb1);
        $this->assertEquals($expectedGrandTotal, $calculatedOrder->grand_total);
        $this->assertEquals($expectedRounding, $calculatedOrder->rounding);
        $this->assertEquals(0, $calculatedOrder->grand_total % 100);
    }

    public function test_bayar_tunai_kembalian_benar_dan_posisi_drawer(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();
        $table = RestoTable::where('outlet_id', $outlet->id)->where('code', 'B1')->firstOrFail();

        $shift = app(OpenShiftAction::class)->handle($cashier->id, $outlet->id, 200_000);
        $session = app(OpenTableSessionAction::class)->handle($table->id, 1, $cashier->id);
        $order = $session->orders->first();

        $menuItem = MenuItem::first();
        app(AddOrderItemAction::class)->handle($order, $menuItem->id, 2); // 2 items
        $order = app(CalculateHidangBillAction::class)->handle($session);

        $tendered = $order->grand_total + 10_000;
        $paidOrder = app(PayOrderAction::class)->handle(
            order: $order,
            paymentMethod: 'cash',
            cashTendered: $tendered,
            shiftId: $shift->id,
            cashierUserId: $cashier->id
        );

        $this->assertEquals(OrderStatus::PAID, $paidOrder->status);
        $this->assertEquals('cash', $paidOrder->payment_method);

        // Check drawer account was touched
        $drawer = LedgerAccount::where('code', "cash:drawer:{$outlet->code}:IDR")->first();
        $this->assertNotNull($drawer);

        $this->artisan('bank:reconcile')->assertExitCode(0);
    }

    public function test_bayar_wallet_saldo_kurang_ditolak(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();
        $customer = User::factory()->create(['role' => 'customer']);
        $table = RestoTable::where('outlet_id', $outlet->id)->where('code', 'B2')->firstOrFail();

        $session = app(OpenTableSessionAction::class)->handle($table->id, 2, $cashier->id, $customer->id);
        $order = $session->orders->first();

        $menuItem = MenuItem::first();
        app(AddOrderItemAction::class)->handle($order, $menuItem->id, 5);
        $order = app(CalculateHidangBillAction::class)->handle($session);

        // Customer has 0 balance, trying to pay order
        $this->expectException(InsufficientFundsException::class);

        app(PayOrderAction::class)->handle(
            order: $order,
            paymentMethod: 'wallet',
            payerUser: $customer
        );
    }

    public function test_submit_ganda_pembayaran_idempoten(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();
        $table = RestoTable::where('outlet_id', $outlet->id)->where('code', 'VIP-1')->firstOrFail();

        $shift = app(OpenShiftAction::class)->handle($cashier->id, $outlet->id, 300_000);
        $session = app(OpenTableSessionAction::class)->handle($table->id, 4, $cashier->id);
        $order = $session->orders->first();

        $menuItem = MenuItem::first();
        app(AddOrderItemAction::class)->handle($order, $menuItem->id, 2);
        $order = app(CalculateHidangBillAction::class)->handle($session);

        $idemKey = 'test_idem_pos_'.Str::uuid();

        // First pay attempt
        $res1 = app(PayOrderAction::class)->handle(
            order: $order,
            paymentMethod: 'cash',
            cashTendered: $order->grand_total,
            idempotencyKey: $idemKey,
            shiftId: $shift->id,
            cashierUserId: $cashier->id
        );

        $this->assertEquals(OrderStatus::PAID, $res1->status);

        // Second pay attempt with same order and key
        $res2 = app(PayOrderAction::class)->handle(
            order: $order,
            paymentMethod: 'cash',
            cashTendered: $order->grand_total,
            idempotencyKey: $idemKey,
            shiftId: $shift->id,
            cashierUserId: $cashier->id
        );

        $this->assertEquals(OrderStatus::PAID, $res2->status);

        // Verify ledger entries not duplicated and reconcile is 0
        $this->artisan('bank:reconcile')->assertExitCode(0);
    }

    public function test_meja_terisi_tidak_bisa_dibuka_ganda(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();
        $table = RestoTable::where('outlet_id', $outlet->id)->where('code', 'L1')->firstOrFail();

        app(OpenTableSessionAction::class)->handle($table->id, 3, $cashier->id);

        // Second attempt on same table
        $this->expectException(TableOccupiedException::class);
        app(OpenTableSessionAction::class)->handle($table->id, 2, $cashier->id);
    }

    public function test_shift_variance_positif_dan_negatif_tercatat_di_ledger(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();

        // 1. Positive variance (cash overage)
        $shift1 = app(OpenShiftAction::class)->handle($cashier->id, $outlet->id, 100_000);
        // Expected is 100_000, counted is 120_000 -> +20_000 variance
        $closedShift1 = app(CloseShiftAction::class)->handle($shift1, 120_000);
        $this->assertEquals(20_000, $closedShift1->variance);
        $this->assertEquals(ShiftStatus::CLOSED, $closedShift1->status);

        // 2. Negative variance (cash shortage)
        $shift2 = app(OpenShiftAction::class)->handle($cashier->id, $outlet->id, 100_000);
        // Expected is 100_000, counted is 85_000 -> -15_000 variance
        $closedShift2 = app(CloseShiftAction::class)->handle($shift2, 85_000);
        $this->assertEquals(-15_000, $closedShift2->variance);
        $this->assertEquals(ShiftStatus::CLOSED, $closedShift2->status);

        $this->artisan('bank:reconcile')->assertExitCode(0);
    }

    public function test_void_order_oleh_manager_membalik_pembukuan_ledger(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();
        $manager = User::where('email', 'manager.resto@autoserve.test')->firstOrFail();
        $table = RestoTable::where('outlet_id', $outlet->id)->where('code', 'O1')->firstOrFail();

        $shift = app(OpenShiftAction::class)->handle($cashier->id, $outlet->id, 100_000);
        $session = app(OpenTableSessionAction::class)->handle($table->id, 2, $cashier->id);
        $order = $session->orders->first();

        $menuItem = MenuItem::first();
        app(AddOrderItemAction::class)->handle($order, $menuItem->id, 2);
        $order = app(CalculateHidangBillAction::class)->handle($session);

        // Pay cash
        app(PayOrderAction::class)->handle(
            order: $order,
            paymentMethod: 'cash',
            shiftId: $shift->id,
            cashierUserId: $cashier->id
        );
        $this->assertEquals(OrderStatus::PAID, $order->fresh()->status);

        // Cashier cannot void
        $this->expectException(InvalidOrderOperationException::class);
        app(VoidOrderAction::class)->handle($order, $cashier, 'Kasir coba void');
    }

    public function test_manager_sukses_void_dan_reconcile_bersih(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();
        $manager = User::where('email', 'manager.resto@autoserve.test')->firstOrFail();
        $table = RestoTable::where('outlet_id', $outlet->id)->where('code', 'A4')->firstOrFail();

        $shift = app(OpenShiftAction::class)->handle($cashier->id, $outlet->id, 100_000);
        $session = app(OpenTableSessionAction::class)->handle($table->id, 2, $cashier->id);
        $order = $session->orders->first();

        $menuItem = MenuItem::first();
        app(AddOrderItemAction::class)->handle($order, $menuItem->id, 1);
        $order = app(CalculateHidangBillAction::class)->handle($session);

        app(PayOrderAction::class)->handle(
            order: $order,
            paymentMethod: 'cash',
            shiftId: $shift->id,
            cashierUserId: $cashier->id
        );

        $voidedOrder = app(VoidOrderAction::class)->handle($order, $manager, 'Salah tagih meja');
        $this->assertEquals(OrderStatus::VOID, $voidedOrder->status);
        $this->assertEquals('Salah tagih meja', $voidedOrder->void_reason);

        $this->artisan('bank:reconcile')->assertExitCode(0);
    }

    public function test_resto_close_day_check_cocok_dengan_ledger(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();
        $table = RestoTable::where('outlet_id', $outlet->id)->where('code', 'A1')->firstOrFail();

        $initialSummary = DailySummary::where('outlet_id', $outlet->id)->whereDate('date', date('Y-m-d'))->first();
        $initialTransactions = $initialSummary ? (int) $initialSummary->transactions : 0;
        $initialGross = $initialSummary ? (int) $initialSummary->gross_sales : 0;

        $shift = app(OpenShiftAction::class)->handle($cashier->id, $outlet->id, 100_000);
        $session = app(OpenTableSessionAction::class)->handle($table->id, 2, $cashier->id);
        $order = $session->orders->first();

        $menuItem = MenuItem::first();
        app(AddOrderItemAction::class)->handle($order, $menuItem->id, 2);
        $order = app(CalculateHidangBillAction::class)->handle($session);

        app(PayOrderAction::class)->handle(
            order: $order,
            paymentMethod: 'cash',
            shiftId: $shift->id,
            cashierUserId: $cashier->id
        );

        // Run close day with check
        $this->artisan('resto:close-day', ['--check' => true])
            ->assertExitCode(0);

        // Ensure daily summary was created and matches accumulated orders
        $this->assertDatabaseHas('resto_daily_summaries', [
            'outlet_id' => $outlet->id,
            'gross_sales' => $initialGross + $order->subtotal,
            'transactions' => $initialTransactions + 1,
        ]);
        $this->assertGreaterThan(0, $initialGross + $order->subtotal);
    }
}
