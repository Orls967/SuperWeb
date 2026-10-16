<?php

declare(strict_types=1);

namespace Modules\Resto\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Resto\Application\Actions\CalculateHidangBillAction;
use Modules\Resto\Application\Actions\OpenShiftAction;
use Modules\Resto\Application\Actions\OpenTableSessionAction;
use Modules\Resto\Application\Actions\PresentHidangAction;
use Modules\Resto\database\seeders\RestoMenuSeeder;
use Modules\Resto\Domain\Enums\BatchStatus;
use Modules\Resto\Domain\Enums\ConsumedState;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\ProductionBatch;
use Modules\Resto\Domain\Models\Recipe;
use Modules\Resto\Domain\Models\RestoTable;
use Tests\TestCase;

class HidangBillIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $cashier;

    private RestoTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RestoMenuSeeder::class);

        $this->outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $this->cashier = User::where('email', 'kasir@autoserve.test')->firstOrFail();
        $this->table = RestoTable::where('outlet_id', $this->outlet->id)->where('code', 'A1')->firstOrFail();

        app(OpenShiftAction::class)->handle(
            cashierId: $this->cashier->id,
            outletId: $this->outlet->id,
            openingFloat: 500_000
        );
    }

    private function createDisplayTray(int $portions = 10, int $costPerPortion = 15_000): DisplayTray
    {
        $menuItem = MenuItem::firstOrFail();
        $recipe = Recipe::firstOrFail();

        $batch = ProductionBatch::create([
            'uuid' => (string) Str::uuid(),
            'outlet_id' => $this->outlet->id,
            'recipe_id' => $recipe->id,
            'menu_item_id' => $menuItem->id,
            'batch_no' => 'BATCH-TEST-'.Str::random(6),
            'cost_total' => $portions * $costPerPortion,
            'cost_per_portion' => $costPerPortion,
            'planned_portions' => $portions,
            'actual_portions' => $portions,
            'cooked_at' => now(),
            'status' => BatchStatus::ON_DISPLAY,
        ]);

        return DisplayTray::create([
            'uuid' => (string) Str::uuid(),
            'outlet_id' => $this->outlet->id,
            'batch_id' => $batch->id,
            'menu_item_id' => $menuItem->id,
            'portions_remaining' => $portions,
            'cost_per_portion' => $costPerPortion,
            'recirculation_count' => 0,
            'placed_at' => now(),
            'expires_at' => now()->addHours(6),
            'status' => TrayStatus::ON_DISPLAY,
        ]);
    }

    public function test_menghitung_tagihan_hidang_dua_kali_memposting_hpp_tepat_sekali_dan_mengurangi_porsi_tray_sekali(): void
    {
        $session = app(OpenTableSessionAction::class)->handle(
            tableId: $this->table->id,
            guestCount: 2,
            openedBy: $this->cashier->id
        );

        $tray = $this->createDisplayTray(portions: 5, costPerPortion: 12_000);
        $order = app(PresentHidangAction::class)->handle($session, [$tray->id => 1]);
        $orderItem = $order->items->first();

        $initialPortions = $tray->fresh()->portions_remaining;
        $this->assertEquals(5, $initialPortions);

        $calcAction = app(CalculateHidangBillAction::class);

        // First calculation: item consumed
        $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'consumed'],
            discount: 0,
            userId: $this->cashier->id
        );

        $trayAfterFirst = $tray->fresh();
        $this->assertEquals(4, $trayAfterFirst->portions_remaining);

        $orderItemAfterFirst = $orderItem->fresh();
        $this->assertEquals(ConsumedState::CONSUMED, $orderItemAfterFirst->consumed_state);

        $cogsAccCode = 'expense:resto:cogs:IDR';
        $cogsEntriesFirst = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', $cogsAccCode))
            ->whereHas('transaction', fn ($q) => $q->where('reference_type', 'resto_order_item')->where('reference_id', $orderItem->id))
            ->count();
        $this->assertEquals(1, $cogsEntriesFirst);

        // Second calculation with same input: must be idempotent!
        $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'consumed'],
            discount: 0,
            userId: $this->cashier->id
        );

        $trayAfterSecond = $tray->fresh();
        // Portion remaining MUST STILL be 4 (not deducted to 3!)
        $this->assertEquals(4, $trayAfterSecond->portions_remaining);

        // COGS entries MUST STILL be 1 (not duplicated!)
        $cogsEntriesSecond = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', $cogsAccCode))
            ->whereHas('transaction', fn ($q) => $q->where('reference_type', 'resto_order_item')->where('reference_id', $orderItem->id))
            ->count();
        $this->assertEquals(1, $cogsEntriesSecond);
    }

    public function test_menghitung_tagihan_hidang_dua_kali_tidak_meresirkulasi_ulang_piring_yang_dikembalikan_utuh(): void
    {
        $session = app(OpenTableSessionAction::class)->handle(
            tableId: $this->table->id,
            guestCount: 2,
            openedBy: $this->cashier->id
        );

        $tray = $this->createDisplayTray(portions: 5, costPerPortion: 15_000);
        $order = app(PresentHidangAction::class)->handle($session, [$tray->id => 1]);
        $orderItem = $order->items->first();

        $calcAction = app(CalculateHidangBillAction::class);

        // First calculation: item returned untouched
        $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'returned'],
            discount: 0,
            userId: $this->cashier->id
        );

        $trayAfterFirst = $tray->fresh();
        $this->assertEquals(1, $trayAfterFirst->recirculation_count);
        $this->assertEquals(5, $trayAfterFirst->portions_remaining);
        $this->assertEquals(ConsumedState::RETURNED, $orderItem->fresh()->consumed_state);

        // Second calculation: recirculateAction MUST NOT be called again
        $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'returned'],
            discount: 0,
            userId: $this->cashier->id
        );

        $trayAfterSecond = $tray->fresh();
        // Recirculation count MUST STILL be 1 (not 2!)
        $this->assertEquals(1, $trayAfterSecond->recirculation_count);
        $this->assertEquals(5, $trayAfterSecond->portions_remaining);
    }

    public function test_memperbarui_diskon_pada_hitung_tagihan_ulang_hanya_mengubah_grand_total_tanpa_efek_samping_stok_atau_ledger(): void
    {
        $session = app(OpenTableSessionAction::class)->handle(
            tableId: $this->table->id,
            guestCount: 2,
            openedBy: $this->cashier->id
        );

        $tray = $this->createDisplayTray(portions: 10, costPerPortion: 20_000);
        $order = app(PresentHidangAction::class)->handle($session, [$tray->id => 2]);
        $orderItem = $order->items->first();

        $calcAction = app(CalculateHidangBillAction::class);

        // First calculation: no discount
        $orderFirst = $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'consumed'],
            discount: 0,
            userId: $this->cashier->id
        );

        $trayPortionsFirst = $tray->fresh()->portions_remaining;
        $this->assertEquals(8, $trayPortionsFirst);
        $initialGrandTotal = $orderFirst->grand_total;

        $cogsAccCode = 'expense:resto:cogs:IDR';
        $cogsEntriesFirst = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', $cogsAccCode))
            ->whereHas('transaction', fn ($q) => $q->where('reference_type', 'resto_order_item')->where('reference_id', $orderItem->id))
            ->count();
        $this->assertEquals(1, $cogsEntriesFirst);

        // Second calculation: apply discount of 10,000
        $orderSecond = $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'consumed'],
            discount: 10_000,
            userId: $this->cashier->id
        );

        $trayPortionsSecond = $tray->fresh()->portions_remaining;
        $this->assertEquals(8, $trayPortionsSecond); // Stok tidak berkurang lagi!

        $cogsEntriesSecond = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', $cogsAccCode))
            ->whereHas('transaction', fn ($q) => $q->where('reference_type', 'resto_order_item')->where('reference_id', $orderItem->id))
            ->count();
        $this->assertEquals(1, $cogsEntriesSecond); // Ledger tidak terposting lagi!

        $this->assertEquals(10_000, $orderSecond->discount);
        $this->assertLessThan($initialGrandTotal, $orderSecond->grand_total);
    }

    public function test_endpoint_kalkulasi_tagihan_pos_resto_oleh_role_berhak_dan_tidak_berhak(): void
    {
        $session = app(OpenTableSessionAction::class)->handle(
            tableId: $this->table->id,
            guestCount: 2,
            openedBy: $this->cashier->id
        );

        $tray = $this->createDisplayTray(portions: 5, costPerPortion: 10_000);
        $order = app(PresentHidangAction::class)->handle($session, [$tray->id => 1]);
        $orderItem = $order->items->first();

        // 1. Role tidak berhak (customer / guest) -> 403 Forbidden
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)
            ->postJson("/resto/pos/session/{$session->id}/bill", [
                'consumed_statuses' => [$orderItem->id => 'consumed'],
                'discount' => 0,
            ])
            ->assertStatus(403);

        // 2. Role berhak (cashier) -> 200 OK
        $this->actingAs($this->cashier)
            ->postJson("/resto/pos/session/{$session->id}/bill", [
                'consumed_statuses' => [$orderItem->id => 'consumed'],
                'discount' => 0,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.status', OrderStatus::AWAITING_PAYMENT->value);

        // 3. Re-request by outlet manager -> 200 OK idempoten
        $manager = User::where('email', 'manager.resto@autoserve.test')->first()
            ?? User::factory()->create(['role' => 'outlet_manager']);

        $this->actingAs($manager)
            ->postJson("/resto/pos/session/{$session->id}/bill", [
                'consumed_statuses' => [$orderItem->id => 'consumed'],
                'discount' => 5_000,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.discount', 5_000);
    }
}
