<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Manufacturing\Application\Services\PlanningService;
use Modules\Manufacturing\Domain\Models\ForecastScenario;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialBalance;
use Modules\Manufacturing\Domain\Models\MaterialReservation;
use Modules\Manufacturing\Domain\Models\MpsHeader;
use Modules\Manufacturing\Domain\Models\MrpRun;
use Modules\Manufacturing\Domain\Models\PlannedOrder;
use Modules\Manufacturing\Domain\Models\WorkCenter;
use Tests\TestCase;

/**
 * Regresi Fase 36: forecast ber-skenario, MPS time fence, MRP idempoten
 * (ledakan BOM + netting + lot sizing), CRP bottleneck, reservasi hard/soft,
 * konflik alokasi, usulan PR via contract, simulasi what-if.
 */
class ProductionPlanningTest extends TestCase
{
    use RefreshDatabase;

    private PlanningService $planning;

    private ManufacturingService $mfg;

    private User $planner;

    private Material $finished;

    private Material $raw;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->planning = app(PlanningService::class);
        $this->mfg = app(ManufacturingService::class);
        $this->planner = User::where('role', 'admin')->firstOrFail();

        $this->finished = $this->mfg->createMaterial(['code' => 'FG-PLAN', 'name' => 'Barang jadi perencanaan', 'kind' => 'finished', 'base_uom' => 'pcs']);
        $this->raw = $this->mfg->createMaterial(['code' => 'RM-PLAN', 'name' => 'Bahan baku perencanaan', 'kind' => 'raw', 'base_uom' => 'kg']);

        $this->mfg->createBom([
            'output_material_id' => $this->finished->id,
            'name' => 'BOM perencanaan',
            'effective_from' => now()->toDateString(),
            'lines' => [['input_material_id' => $this->raw->id, 'qty' => 2, 'uom' => 'kg']],
        ], $this->planner);
    }

    private function activeMps(int $qty = 10, ?string $period = null): void
    {
        $this->planning->createMps(
            'MPS perencanaan',
            [['material_id' => $this->finished->id, 'period_start' => $period ?? now()->addDays(10)->toDateString(), 'qty' => $qty]],
            $this->planner,
            freezeDays: 0,
        );
    }

    // ── 36.1 Forecast skenario ───────────────────────────────────────────

    public function test_forecast_scenarios_are_versioned_and_only_one_active(): void
    {
        $first = $this->planning->createForecastScenario('Distribusi Q4', [
            ['material_id' => $this->finished->id, 'period_start' => now()->addDays(7)->toDateString(), 'qty' => 5],
        ], $this->planner);

        $second = $this->planning->createForecastScenario('Distribusi Q4', [
            ['material_id' => $this->finished->id, 'period_start' => now()->addDays(7)->toDateString(), 'qty' => 9],
        ], $this->planner);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);

        $this->planning->activateForecastScenario($first);
        $this->planning->activateForecastScenario($second);

        $active = ForecastScenario::where('status', 'active')->get();
        $this->assertCount(1, $active);
        $this->assertTrue($first->fresh()->status === 'archived');
    }

    // ── 36.2 MPS time fence ──────────────────────────────────────────────

    public function test_mps_freezes_lines_within_time_fence_and_archives_previous(): void
    {
        $this->planning->createMps('MPS-A', [
            ['material_id' => $this->finished->id, 'period_start' => now()->addDay()->toDateString(), 'qty' => 3],
            ['material_id' => $this->finished->id, 'period_start' => now()->addDays(30)->toDateString(), 'qty' => 4],
        ], $this->planner, freezeDays: 14);

        $second = $this->planning->createMps('MPS-B', [
            ['material_id' => $this->finished->id, 'period_start' => now()->addDays(40)->toDateString(), 'qty' => 2],
        ], $this->planner, freezeDays: 14);

        $lines = $second->lines;
        $this->assertFalse($lines->first()->frozen, 'Periode di luar fence tidak beku.');
        $this->assertSame(1, MpsHeader::where('status', 'active')->count());
    }

    // ── 36.3 MRP ledakan BOM + netting + idempoten ───────────────────────

    public function test_mrp_explodes_bom_nets_stock_and_is_idempotent(): void
    {
        $this->activeMps(10);
        MaterialBalance::create(['material_id' => $this->raw->id, 'qty_on_hand' => 5, 'qty_reserved' => 0]);
        $this->planning->savePlanningParams($this->raw->id, ['lead_time_days' => 7, 'moq' => 1, 'lot_sizing' => 'l4l', 'safety_stock' => 0, 'reorder_point' => 0]);

        $run = $this->planning->runMrp(horizonDays: 28, bucketDays: 7);

        $this->assertSame('completed', $run->status);
        $this->assertSame(1, PlannedOrder::where('kind', 'production')->count(), 'Satu planned order produksi untuk FG.');
        $this->assertSame(1, PlannedOrder::where('kind', 'purchase')->count(), 'Komponen dibeli, bukan diproduksi.');

        // 10 FG × 2 kg = 20 kg dibutuhkan, 5 on-hand → beli 15 (l4l).
        $purchase = PlannedOrder::where('kind', 'purchase')->firstOrFail();
        $this->assertEqualsWithDelta(15.0, (float) $purchase->qty, 0.0001);

        // Replay: run_key sama → tidak membuat run/order baru.
        $again = $this->planning->runMrp(horizonDays: 28, bucketDays: 7);
        $this->assertSame($run->id, $again->id);
        $this->assertSame(2, PlannedOrder::count());
    }

    // ── 36.3 lot sizing ──────────────────────────────────────────────────

    public function test_lot_sizing_strategies_and_moq_floor(): void
    {
        $materialId = $this->raw->id;
        $param = $this->planning->savePlanningParams($materialId, [
            'lead_time_days' => 7, 'safety_stock' => 0, 'reorder_point' => 0, 'moq' => 50,
            'lot_sizing' => 'l4l',
        ]);

        // MOQ memaksa qty minimal.
        $this->assertEqualsWithDelta(50.0, (float) $this->planning->lotSize('15', $param, $materialId), 0.0001);

        $param->update(['lot_sizing' => 'fixed', 'fixed_order_qty' => 120]);
        $this->assertEqualsWithDelta(120.0, (float) $this->planning->lotSize('15', $param, $materialId), 0.0001);

        $param->update(['lot_sizing' => 'l4l', 'moq' => 1]);
        $this->assertEqualsWithDelta(15.0, (float) $this->planning->lotSize('15', $param, $materialId), 0.0001);
    }

    // ── 36.4 CRP bottleneck ──────────────────────────────────────────────

    public function test_crp_marks_bottleneck_when_load_exceeds_capacity(): void
    {
        $plant = $this->mfg->createPlant(['code' => 'PLT-CRP', 'name' => 'Pabrik CRP', 'type' => 'factory']);
        $wc = WorkCenter::create([
            'plant_id' => $plant->id, 'code' => 'WC-CRP', 'name' => 'Work center sempit', 'kind' => 'machine',
            'capacity_per_hour' => 1, 'efficiency_percent' => 100,
            'machine_cost_per_hour_idr' => 0, 'labor_cost_per_hour_idr' => 0, 'overhead_per_hour_idr' => 0,
        ]);
        $this->mfg->createRouting([
            'output_material_id' => $this->finished->id, 'name' => 'Routing CRP',
            'effective_from' => now()->toDateString(),
            'operations' => [['name' => 'Press', 'work_center_id' => $wc->id, 'setup_minutes' => 60, 'run_minutes_per_unit' => 60]],
        ]);

        $this->activeMps(10);
        MaterialBalance::create(['material_id' => $this->raw->id, 'qty_on_hand' => 100, 'qty_reserved' => 0]);

        $run = $this->planning->runMrp(horizonDays: 28, bucketDays: 7);
        $run->load('capacityLoads');

        $this->assertGreaterThan(0, $run->capacityLoads->count(), 'CRP menghasilkan baris beban.');
        $this->assertGreaterThan(0, (int) $run->summary['bottlenecks'], '10 unit × 60 menit >> kapasitas 1 bucket.');
        $this->assertTrue($run->capacityLoads->where('bottleneck', true)->isNotEmpty());
    }

    // ── 36.5 firm + reservasi + konflik alokasi ──────────────────────────

    public function test_firming_creates_soft_and_hard_reservations(): void
    {
        $this->activeMps(10);
        MaterialBalance::create(['material_id' => $this->raw->id, 'qty_on_hand' => 25, 'qty_reserved' => 0]);
        $this->runToOrders();

        $productionOrder = PlannedOrder::where('kind', 'production')->firstOrFail();
        $firmed = $this->planning->firmPlannedOrder($productionOrder, 'soft');

        $this->assertSame('firm', $firmed->status);
        $this->assertSame('soft', $firmed->reservation_kind);
        // 10 FG × 2 kg = 20 kg, stok 25 → penuh.
        $reservation = $firmed->reservations()->where('material_id', $this->raw->id)->firstOrFail();
        $this->assertEqualsWithDelta(20.0, (float) $reservation->qty, 0.0001);
        $this->assertNull($reservation->shortfall_note);
    }

    public function test_allocation_conflict_prioritizes_earlier_due_date(): void
    {
        $this->planning->createMps('MPS konflik', [
            ['material_id' => $this->finished->id, 'period_start' => now()->addDays(7)->toDateString(), 'qty' => 10],
            ['material_id' => $this->finished->id, 'period_start' => now()->addDays(14)->toDateString(), 'qty' => 10],
        ], $this->planner, freezeDays: 0);
        MaterialBalance::create(['material_id' => $this->raw->id, 'qty_on_hand' => 30, 'qty_reserved' => 0]);
        $this->runToOrders();

        $orders = PlannedOrder::where('kind', 'production')->orderBy('due_date')->get();
        $this->assertGreaterThanOrEqual(2, $orders->count(), 'Butuh ≥2 order produksi untuk konflik.');

        foreach ($orders as $order) {
            $this->planning->firmPlannedOrder($order, 'hard');
        }

        // Total kebutuhan 2 × 10 × 2 = 40 kg > 30 kg → konflik.
        $allocations = $this->planning->resolveAllocationConflicts();
        $this->assertEqualsWithDelta(30.0, array_sum($allocations), 0.0001);

        $first = MaterialReservation::where('planned_order_id', $orders->first()->id)
            ->where('material_id', $this->raw->id)->firstOrFail();
        $this->assertNull($first->shortfall_note, 'Order terdahulu diprioritaskan penuh.');

        $shortfalls = MaterialReservation::whereNotNull('shortfall_note')->get();
        $this->assertTrue($shortfalls->isNotEmpty(), 'Order kemudian mencatat shortfall.');
    }

    // ── 36.6 Usulan PR via contract ──────────────────────────────────────

    public function test_propose_purchases_creates_pr_and_marks_orders(): void
    {
        $this->activeMps(10);
        MaterialBalance::create(['material_id' => $this->raw->id, 'qty_on_hand' => 0, 'qty_reserved' => 0]);
        $run = $this->runToOrders();

        $numbers = $this->planning->proposePurchases($run, $this->planner);

        $this->assertNotEmpty($numbers);
        $this->assertStringStartsWith('PR/', $numbers[0]);
        $this->assertSame(1, PlannedOrder::whereNotNull('pr_ref')->count());

        // Tidak ada duplikat: PR kedua tidak dibuat untuk order yang sudah punya PR.
        $this->assertSame([], $this->planning->proposePurchases($run, $this->planner));
    }

    // ── 36.7 what-if tidak mengubah data nyata ───────────────────────────

    public function test_what_if_scenario_restores_planning_params(): void
    {
        $param = $this->planning->savePlanningParams($this->raw->id, [
            'lead_time_days' => 7, 'safety_stock' => 10, 'reorder_point' => 5, 'moq' => 1, 'lot_sizing' => 'l4l',
        ]);
        $this->activeMps(10);

        $run = $this->planning->whatIf([
            ['material_id' => $this->raw->id, 'safety_stock' => 1000],
        ]);

        $this->assertTrue($run->is_scenario);
        $this->assertSame('completed', $run->status);
        $param->refresh();
        $this->assertEqualsWithDelta(10.0, (float) $param->safety_stock, 0.0001, 'Safety stock dipulihkan.');
        // Mode scenario tidak menulis planned order nyata.
        $this->assertSame(0, PlannedOrder::where('status', 'planned')->count());
    }

    // ── 36.8 Command terjadwal ───────────────────────────────────────────

    public function test_run_mrp_command_runs_and_reports(): void
    {
        $this->activeMps(5);
        MaterialBalance::create(['material_id' => $this->raw->id, 'qty_on_hand' => 0, 'qty_reserved' => 0]);

        $this->artisan('mfg:run-mrp', ['--horizon' => 28, '--bucket' => 7])
            ->assertSuccessful();
    }

    private function runToOrders(): MrpRun
    {
        $run = $this->planning->runMrp(horizonDays: 28, bucketDays: 7);
        $this->assertSame('completed', $run->status);

        return $run;
    }
}
