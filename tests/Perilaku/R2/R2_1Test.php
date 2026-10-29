<?php

declare(strict_types=1);

namespace Tests\Perilaku\R2;

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
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\ProductionBatch;
use Modules\Resto\Domain\Models\Recipe;
use Modules\Resto\Domain\Models\RestoTable;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('perilaku')]
class R2_1Test extends TestCase
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
            'batch_no' => 'BATCH-R21-'.Str::random(6),
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

    public function test_klausul_1_dan_3_hitung_tagihan_hidang_2x_hpp_sama_tidak_dobel_dan_item_consumed_tidak_diproses_ulang(): void
    {
        $session = app(OpenTableSessionAction::class)->handle(
            tableId: $this->table->id,
            guestCount: 2,
            openedBy: $this->cashier->id
        );

        $tray = $this->createDisplayTray(portions: 5, costPerPortion: 12_000);
        $order = app(PresentHidangAction::class)->handle($session, [$tray->id => 1]);
        $orderItem = $order->items->first();

        $calcAction = app(CalculateHidangBillAction::class);

        // Panggilan 1
        $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'consumed'],
            discount: 0,
            userId: $this->cashier->id
        );

        $cogsAccCode = 'expense:resto:cogs:IDR';
        $cogsEntriesFirst = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', $cogsAccCode))
            ->whereHas('transaction', fn ($q) => $q->where('reference_type', 'resto_order_item')->where('reference_id', $orderItem->id))
            ->count();
        $this->assertEquals(1, $cogsEntriesFirst);
        $this->assertEquals(4, $tray->fresh()->portions_remaining);

        // Panggilan 2
        $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'consumed'],
            discount: 0,
            userId: $this->cashier->id
        );

        $cogsEntriesSecond = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', $cogsAccCode))
            ->whereHas('transaction', fn ($q) => $q->where('reference_type', 'resto_order_item')->where('reference_id', $orderItem->id))
            ->count();

        // Klausul 1 & 3 Terbukti: HPP sama, tidak dobel, porsi tidak berkurang 2x
        $this->assertEquals(1, $cogsEntriesSecond, 'Klausul 1 & 3: HPP terposting tepat 1 kali');
        $this->assertEquals(4, $tray->fresh()->portions_remaining, 'Klausul 1 & 3: Porsi tray tidak berkurang dua kali');
    }

    public function test_klausul_2_item_yang_sudah_diputuskan_returned_tidak_diresirkulasi_ulang(): void
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

        // Putuskan RETURNED
        $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'returned'],
            discount: 0,
            userId: $this->cashier->id
        );

        $this->assertEquals(ConsumedState::RETURNED, $orderItem->fresh()->consumed_state);
        $this->assertEquals(1, $tray->fresh()->recirculation_count);

        // Panggilan ulang kedua
        $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'returned'],
            discount: 0,
            userId: $this->cashier->id
        );

        // Klausul 2 Terbukti: Resirkulasi tetap 1, tidak diresirkulasi ulang
        $this->assertEquals(1, $tray->fresh()->recirculation_count, 'Klausul 2: Piring RETURNED tidak diresirkulasi berulang');
    }
}
