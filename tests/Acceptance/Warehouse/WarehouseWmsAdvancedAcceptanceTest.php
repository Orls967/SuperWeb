<?php

declare(strict_types=1);

namespace Tests\Acceptance\Warehouse;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Wms\Application\Actions\PostOpeningBalanceAction;
use Modules\Wms\Application\Services\WmsService;
use Modules\Wms\Contracts\StockAvailabilityReader;
use Modules\Wms\Contracts\WarehouseValuationReader;
use Modules\Wms\Domain\Algorithms\CycleCountScheduler;
use Modules\Wms\Domain\Algorithms\PickPathPlanner;
use Modules\Wms\Domain\Algorithms\PutawaySlotter;
use Modules\Wms\Domain\StateMachines\WarehouseTaskStateMachine;
use Tests\TestCase;

/**
 * Acceptance Test Fase 41 — WMS Lanjutan: Gudang, Penilaian Persediaan & Robotika Dasar
 *
 * Kriteria T-10:
 *  1. PutawaySlotter (H1) sesuai vektor prompt (A/B/C single-bin, split greedy, NO_CAPACITY)
 *  2. PickPathPlanner (H2) membandingkan S_SHAPE vs LARGEST_GAP & tie-break S_SHAPE
 *  3. CycleCountScheduler (H3) menjadwalkan overdue + capacity + carried_over
 *  4. WarehouseTaskStateMachine 7-state (created -> assigned -> in_progress -> confirmed -> closed + exception + cancelled)
 *  5. PostOpeningBalanceAction idempoten + WarehouseValuationReader cocok dengan ledger wms:inventory:*:IDR
 */
class WarehouseWmsAdvancedAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private WmsService $wms;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->wms = app(WmsService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    public function test_putaway_slotter_matches_abc_and_split_greedy_rules(): void
    {
        $slotter = app(PutawaySlotter::class);

        $bins = [
            ['id' => 'B1', 'distance' => 10, 'freeCapacity' => 20],
            ['id' => 'B2', 'distance' => 30, 'freeCapacity' => 12],
            ['id' => 'B3', 'distance' => 50, 'freeCapacity' => 20],
        ];

        // Kelas A q=10: B1 jarak terdekat (10) meski free-q=10 >= 2
        $resA = $slotter->plan(10, 'A', $bins);
        $this->assertSame('B1', $resA['allocations'][0]['bin_id']);
        $this->assertSame(10, $resA['allocations'][0]['qty']);

        // Kelas B q=10: B2 best-fit (free-q=2)
        $resB = $slotter->plan(10, 'B', $bins);
        $this->assertSame('B2', $resB['allocations'][0]['bin_id']);

        // Kelas C q=10: B3 terjauh (distance=50)
        $resC = $slotter->plan(10, 'C', $bins);
        $this->assertSame('B3', $resC['allocations'][0]['bin_id']);

        // Split greedy q=35 kelas A: B1(20) + B2(12) + B3(3)
        $resSplit = $slotter->plan(35, 'A', $bins);
        $this->assertSame([
            ['bin_id' => 'B1', 'qty' => 20],
            ['bin_id' => 'B2', 'qty' => 12],
            ['bin_id' => 'B3', 'qty' => 3],
        ], $resSplit['allocations']);
    }

    public function test_pick_path_planner_compares_sshape_and_largest_gap(): void
    {
        $planner = app(PickPathPlanner::class);

        $plan = $planner->plan(10, 100, [
            ['aisle' => 1, 'pos' => 10],
            ['aisle' => 1, 'pos' => 90],
            ['aisle' => 2, 'pos' => 5],
        ]);

        $this->assertContains($plan['strategy'], ['S_SHAPE', 'LARGEST_GAP']);
        $this->assertGreaterThan(0, $plan['total_distance']);
        $this->assertCount(3, $plan['sequence']);
    }

    public function test_cycle_count_scheduler_prioritizes_overdue_and_respects_capacity(): void
    {
        $scheduler = app(CycleCountScheduler::class);

        $result = $scheduler->schedule(400, [
            ['id' => 'SKU-C', 'abcClass' => 'C', 'lastCountedDay' => 10],   // due 370 -> overdue 30
            ['id' => 'SKU-A1', 'abcClass' => 'A', 'lastCountedDay' => 360], // due 390 -> overdue 10
            ['id' => 'SKU-A2', 'abcClass' => 'A', 'lastCountedDay' => 340], // due 370 -> overdue 30
            ['id' => 'SKU-B', 'abcClass' => 'B', 'lastCountedDay' => 350],  // due 440 -> not due
        ], 2);

        $this->assertSame(['SKU-A2', 'SKU-C'], array_column($result['selected'], 'id'));
        $this->assertSame(['SKU-A1'], array_column($result['carried_over'], 'id'));
    }

    public function test_warehouse_task_state_machine_enforces_7_state_transitions(): void
    {
        $sm = app(WarehouseTaskStateMachine::class);

        $this->assertSame('assigned', $sm->nextState('created', 'assign'));
        $this->assertSame('in_progress', $sm->nextState('assigned', 'start'));
        $this->assertSame('confirmed', $sm->nextState('in_progress', 'confirm'));
        $this->assertSame('closed', $sm->nextState('confirmed', 'close'));
        $this->assertSame('exception', $sm->nextState('in_progress', 'flag_exception'));
        $this->assertSame('cancelled', $sm->nextState('created', 'cancel'));
        $this->assertFalse($sm->canTransition('closed', 'assign'));
    }

    public function test_opening_balance_is_idempotent_and_matches_valuation_reader(): void
    {
        $warehouse = $this->wms->createWarehouse([
            'code' => 'DC-VAL',
            'name' => 'DC Valuation',
            'kind' => 'dc',
            'city' => 'Banjarmasin',
        ]);
        $zone = $this->wms->createZone($warehouse, 'STO', 'Storage', 'storage');
        $rack = $this->wms->createRack($zone, 'R1');
        $bin = $this->wms->createBin($rack, 'B01', 100);

        $productId = 101;

        DB::table('wms_bin_stocks')->insert([
            'bin_id' => $bin->id,
            'product_id' => $productId,
            'qty' => 10,
            'qty_base' => 10,
            'stock_status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wms_unit_costs')->insert([
            'product_id' => $productId,
            'warehouse_id' => $warehouse->id,
            'cost_idr' => 25000,
            'method' => 'std',
            'effective_date' => '2026-11-01',
            'created_by_user_id' => $this->admin->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $action = app(PostOpeningBalanceAction::class);
        $first = $action->execute($warehouse->id, (int) $this->admin->id, 'IDEM:WMS:OPENING:DC-VAL');
        $second = $action->execute($warehouse->id, (int) $this->admin->id, 'IDEM:WMS:OPENING:DC-VAL');

        $this->assertSame(250000, $first['total_value_idr']);
        $this->assertTrue($second['already_posted']);

        $valuation = app(WarehouseValuationReader::class)->getWarehouseValuationIdr($warehouse->id);
        $this->assertSame(250000, $valuation['subledger_value_idr']);
        $this->assertSame(250000, $valuation['ledger_balance_idr']);
        $this->assertSame(0, $valuation['discrepancy_idr']);

        $avail = app(StockAvailabilityReader::class)->getAvailableQty($productId, $warehouse->id);
        $this->assertSame(10, $avail);
    }
}
