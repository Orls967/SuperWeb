<?php

declare(strict_types=1);

namespace Modules\Manufacturing\tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Manufacturing\Application\Actions\CalculateEquipmentOeeMetricsAction;
use Modules\Manufacturing\Application\Actions\CalculateStandardVsActualCostVarianceAction;
use Modules\Manufacturing\Application\Actions\ExplodeMultiLevelBomAction;
use Modules\Manufacturing\Application\Actions\IssueMaintenanceLotoSafetyPermitAction;
use Modules\Manufacturing\Application\Actions\RecordShopFloorWorkOrderOutputAction;
use Modules\Manufacturing\Application\Actions\RunMrpNetRequirementPlanningAction;
use Modules\Manufacturing\Application\Actions\ScheduleCrpFiniteCapacityAction;
use Modules\Manufacturing\Application\Actions\TriggerQualityLotRecallAction;
use Modules\Manufacturing\Domain\Models\Bom;
use Modules\Manufacturing\Domain\Models\BomLine;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Domain\Models\MrpRun;
use Modules\Manufacturing\Domain\Models\Plant;
use Modules\Manufacturing\Domain\Models\ProductionOrder;
use Modules\Manufacturing\Domain\Models\WorkCenter;
use Tests\TestCase;

class ManufacturingActionsAndCostingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    private function createSampleMaterialsAndBom(): array
    {
        $fg = Material::create([
            'code' => 'FG-'.uniqid(),
            'name' => 'Finished Goods Assembly',
            'kind' => 'fg',
            'base_uom' => 'pcs',
            'is_active' => true,
        ]);

        $sub = Material::create([
            'code' => 'SUB-'.uniqid(),
            'name' => 'Sub Assembly Component',
            'kind' => 'sub',
            'base_uom' => 'pcs',
            'is_active' => true,
        ]);

        $raw = Material::create([
            'code' => 'RAW-'.uniqid(),
            'name' => 'Raw Steel Material',
            'kind' => 'raw',
            'base_uom' => 'kg',
            'is_active' => true,
        ]);

        $bomFg = Bom::create([
            'output_material_id' => $fg->id,
            'version' => 1,
            'name' => 'BOM FG v1',
            'output_qty' => 1.0,
            'output_uom' => 'pcs',
            'effective_from' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        BomLine::create([
            'bom_id' => $bomFg->id,
            'input_material_id' => $sub->id,
            'qty' => 2.0,
            'uom' => 'pcs',
            'scrap_percent' => 5.0,
            'sequence' => 1,
        ]);

        $bomSub = Bom::create([
            'output_material_id' => $sub->id,
            'version' => 1,
            'name' => 'BOM Sub v1',
            'output_qty' => 1.0,
            'output_uom' => 'pcs',
            'effective_from' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        BomLine::create([
            'bom_id' => $bomSub->id,
            'input_material_id' => $raw->id,
            'qty' => 3.0,
            'uom' => 'kg',
            'scrap_percent' => 2.0,
            'sequence' => 1,
        ]);

        return [$fg, $sub, $raw, $bomFg, $bomSub];
    }

    private function createSamplePlantAndWorkCenter(): array
    {
        $plant = Plant::create([
            'code' => 'PL-'.substr(uniqid(), 0, 6),
            'name' => 'Main Assembly Plant',
            'city' => 'Cikarang',
            'is_active' => true,
        ]);

        $workCenter = WorkCenter::create([
            'plant_id' => $plant->id,
            'code' => 'WC-CNC-01',
            'name' => 'CNC Milling Station',
            'kind' => 'machine',
            'capacity_per_hour' => 10,
            'capacity_uom' => 'units',
            'efficiency_percent' => 90,
            'machine_cost_per_hour_idr' => 150_000,
            'labor_cost_per_hour_idr' => 80_000,
            'overhead_per_hour_idr' => 50_000,
            'is_active' => true,
        ]);

        return [$plant, $workCenter];
    }

    public function test_explodes_multi_level_bom_recursively_with_scrap_percentages(): void
    {
        [, , $raw, $bomFg] = $this->createSampleMaterialsAndBom();

        $action = app(ExplodeMultiLevelBomAction::class);
        $result = $action->execute($bomFg, 10.0);

        $this->assertEquals(10.0, $result['requested_qty']);
        $this->assertCount(2, $result['components']);
        // Verify leaf raw material requirement computed
        $leafComponent = collect($result['components'])->firstWhere('material_id', $raw->id);
        $this->assertNotNull($leafComponent);
        $this->assertTrue($leafComponent['is_leaf']);
        $this->assertEquals(2, $leafComponent['level']);
        $this->assertGreaterThan(60.0, $leafComponent['qty']);
    }

    public function test_rejects_bom_explosion_when_requested_quantity_is_non_positive(): void
    {
        [, , , $bomFg] = $this->createSampleMaterialsAndBom();

        $action = app(ExplodeMultiLevelBomAction::class);
        $this->expectException(InvalidArgumentException::class);
        $action->execute($bomFg, 0.0);
    }

    public function test_runs_mrp_net_requirement_planning_and_generates_planned_orders(): void
    {
        [$fg, , $raw] = $this->createSampleMaterialsAndBom();

        $action = app(RunMrpNetRequirementPlanningAction::class);
        $runKey = 'MRP-RUN-'.uniqid();
        $mrpRun = $action->execute($runKey, [
            ['material_id' => $fg->id, 'gross_req' => 100.0, 'due_date' => now()->addDays(14)->toDateString()],
            ['material_id' => $raw->id, 'gross_req' => 500.0, 'due_date' => now()->addDays(7)->toDateString()],
        ]);

        $this->assertInstanceOf(MrpRun::class, $mrpRun);
        $this->assertEquals('completed', $mrpRun->status);
        $this->assertCount(2, $mrpRun->requirements);
        $this->assertCount(2, $mrpRun->plannedOrders);

        $purchaseOrder = $mrpRun->plannedOrders->firstWhere('material_id', $raw->id);
        $this->assertEquals('purchase', $purchaseOrder->kind);
    }

    public function test_rejects_mrp_run_with_empty_demands_list(): void
    {
        $action = app(RunMrpNetRequirementPlanningAction::class);
        $this->expectException(InvalidArgumentException::class);
        $action->execute('MRP-EMPTY', []);
    }

    public function test_schedules_crp_finite_capacity_and_detects_work_center_bottleneck(): void
    {
        [, $wc] = $this->createSamplePlantAndWorkCenter();
        $mrpRun = MrpRun::create([
            'run_key' => 'CRP-TEST-'.uniqid(),
            'status' => 'completed',
            'is_scenario' => false,
            'horizon_days' => 30,
            'bucket_days' => 1,
            'started_at' => now(),
        ]);

        $action = app(ScheduleCrpFiniteCapacityAction::class);
        // Available capacity is around 480 * 90% = 432 minutes. Request 600 minutes to trigger bottleneck.
        $loads = $action->execute($mrpRun, now()->toDateString(), [
            ['work_center_id' => (string) $wc->id, 'required_minutes' => 600],
        ]);

        $this->assertCount(1, $loads);
        $this->assertEquals(600, $loads[0]->load_minutes);
        $this->assertTrue((bool) $loads[0]->bottleneck);
        $this->assertGreaterThan(100.0, (float) $loads[0]->utilization_percent);
    }

    public function test_records_shop_floor_work_order_output_and_updates_progress(): void
    {
        [$fg] = $this->createSampleMaterialsAndBom();
        [$plant] = $this->createSamplePlantAndWorkCenter();

        $order = ProductionOrder::create([
            'number' => 'WO-'.uniqid(),
            'material_id' => $fg->id,
            'plant_id' => $plant->id,
            'qty' => 50.0,
            'qty_completed' => 0.0,
            'status' => 'released',
            'scrap_tolerance_percent' => 5.0,
        ]);

        $action = app(RecordShopFloorWorkOrderOutputAction::class);
        $updatedOrder = $action->execute($order, 20.0, 1.0, 4.0, 3.5);

        $this->assertEquals('in_progress', $updatedOrder->status);
        $this->assertEquals(20.0, (float) $updatedOrder->qty_completed);

        // Record remaining to complete
        $completedOrder = $action->execute($updatedOrder, 30.0, 0.5, 5.0, 5.0);
        $this->assertEquals('completed', $completedOrder->status);
        $this->assertEquals(50.0, (float) $completedOrder->qty_completed);
    }

    public function test_rejects_shop_floor_output_on_already_completed_or_cancelled_order(): void
    {
        [$fg] = $this->createSampleMaterialsAndBom();
        [$plant] = $this->createSamplePlantAndWorkCenter();

        $order = ProductionOrder::create([
            'number' => 'WO-CANCELLED-'.uniqid(),
            'material_id' => $fg->id,
            'plant_id' => $plant->id,
            'qty' => 50.0,
            'qty_completed' => 50.0,
            'status' => 'completed',
        ]);

        $action = app(RecordShopFloorWorkOrderOutputAction::class);
        $this->expectException(InvalidArgumentException::class);
        $action->execute($order, 10.0);
    }

    public function test_calculates_standard_vs_actual_cost_variance_and_records_variances_in_integer(): void
    {
        [$fg] = $this->createSampleMaterialsAndBom();
        [$plant] = $this->createSamplePlantAndWorkCenter();

        $order = ProductionOrder::create([
            'number' => 'WO-COST-'.uniqid(),
            'material_id' => $fg->id,
            'plant_id' => $plant->id,
            'qty' => 10.0,
            'qty_completed' => 10.0,
            'status' => 'completed',
        ]);

        $action = app(CalculateStandardVsActualCostVarianceAction::class);
        $result = $action->execute($order, [
            'standard_material_cost_idr' => 1_000_000,
            'actual_material_cost_idr' => 1_150_000, // +150k unfavourable
            'standard_labor_cost_idr' => 500_000,
            'actual_labor_cost_idr' => 450_000,     // -50k favourable
            'standard_overhead_cost_idr' => 300_000,
            'actual_overhead_cost_idr' => 320_000,   // +20k unfavourable
        ]);

        $this->assertEquals(150_000, $result['material_usage_variance_idr']);
        $this->assertEquals(-50_000, $result['labor_efficiency_variance_idr']);
        $this->assertEquals(20_000, $result['overhead_variance_idr']);
        $this->assertEquals(120_000, $result['total_net_variance_idr']);
        $this->assertCount(3, $result['records']);
    }

    public function test_rejects_cost_variance_calculation_when_inputs_are_negative(): void
    {
        [$fg] = $this->createSampleMaterialsAndBom();
        [$plant] = $this->createSamplePlantAndWorkCenter();

        $order = ProductionOrder::create([
            'number' => 'WO-ERR-'.uniqid(),
            'material_id' => $fg->id,
            'plant_id' => $plant->id,
            'qty' => 1.0,
            'status' => 'completed',
        ]);

        $action = app(CalculateStandardVsActualCostVarianceAction::class);
        $this->expectException(InvalidArgumentException::class);
        $action->execute($order, [
            'standard_material_cost_idr' => -100,
            'actual_material_cost_idr' => 500,
        ]);
    }

    public function test_triggers_quality_lot_recall_and_quarantines_affected_lot(): void
    {
        [$fg] = $this->createSampleMaterialsAndBom();
        $lot = MaterialLot::create([
            'material_id' => $fg->id,
            'lot_number' => 'LOT-REC-'.uniqid(),
            'initial_qty' => 100.0,
            'status' => 'available',
            'qc_passed' => true,
        ]);

        $action = app(TriggerQualityLotRecallAction::class);
        $recall = $action->execute($lot, 'Kontaminasi partikel asing pada batch', 25_000_000);

        $this->assertEquals('initiated', $recall->status);
        $this->assertEquals(25_000_000, $recall->cost_idr);
        $this->assertEquals('quarantine', $lot->fresh()->status);
        $this->assertFalse((bool) $lot->fresh()->qc_passed);
    }

    public function test_calculates_overall_equipment_effectiveness_oee_metrics_accurately(): void
    {
        [, $wc] = $this->createSamplePlantAndWorkCenter();

        $action = app(CalculateEquipmentOeeMetricsAction::class);
        $summary = $action->execute(
            workCenter: $wc,
            periodDate: now()->toDateString(),
            shiftCode: 'SHIFT-1',
            plannedMinutes: 480,
            runMinutes: 432, // 90% availability
            idealCycleSeconds: 60,
            totalQty: 400.0, // 400 * 60s / (432 * 60s) = 92.59% performance
            goodQty: 390.0   // 390 / 400 = 97.5% quality
        );

        $this->assertEquals(90.0, (float) $summary->availability_percent);
        $this->assertGreaterThan(92.0, (float) $summary->performance_percent);
        $this->assertEquals(97.5, (float) $summary->quality_percent);
        $this->assertGreaterThan(80.0, (float) $summary->oee_percent);
    }

    public function test_issues_lockout_tagout_loto_safety_permit_with_active_validity_window(): void
    {
        [, $wc] = $this->createSamplePlantAndWorkCenter();

        $action = app(IssueMaintenanceLotoSafetyPermitAction::class);
        $validFrom = now();
        $validUntil = now()->addHours(6);

        $permit = $action->execute(
            workCenter: $wc,
            permitType: 'loto',
            hazards: 'Tegangan tinggi 380V dan hidrolik bertekanan',
            controls: 'Gembok fisik LOTO dipasang, kunci dipegang teknisi utama, tekanan hidrolik dibuang',
            validFrom: $validFrom,
            validUntil: $validUntil,
            requestedByUserId: $this->admin->id
        );

        $this->assertEquals('active', $permit->status);
        $this->assertTrue($permit->isActiveAt($validFrom->copy()->addHour()));
        $this->assertFalse($permit->isActiveAt($validUntil->copy()->addHour()));
    }
}
