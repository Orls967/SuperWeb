<?php

declare(strict_types=1);

namespace Modules\Wms\tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Wms\Application\Actions\AllocateOutboundWavePickingAction;
use Modules\Wms\Application\Actions\Calculate3PlStorageHandlingBillingAction;
use Modules\Wms\Application\Actions\ExecuteDirectedPutawayAction;
use Modules\Wms\Application\Actions\ProcessCrossDockingTransferAction;
use Modules\Wms\Application\Actions\RecordCycleCountAdjustmentAction;
use Modules\Wms\Application\Services\WmsService;
use Modules\Wms\Domain\Models\Bin;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\DockAppointment;
use Modules\Wms\Domain\Models\Rack;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Models\Zone;
use Tests\TestCase;

class WmsFullOperationsTest extends TestCase
{
    use RefreshDatabase;

    private WmsService $service;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->service = app(WmsService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->operator = User::where('email', 'mekanik@autoserve.test')->firstOrFail();
    }

    private function createWarehouseStructure(int $binCapacity = 100): array
    {
        $wh = Warehouse::create([
            'code' => 'WH-'.substr(md5((string) mt_rand()), 0, 6),
            'name' => 'Gudang Uji',
            'kind' => 'dc',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);
        $zone = Zone::create([
            'warehouse_id' => $wh->id,
            'code' => 'Z1',
            'name' => 'Zona 1',
            'kind' => 'storage',
            'is_active' => true,
        ]);
        $rack = Rack::create([
            'zone_id' => $zone->id,
            'code' => 'R1',
        ]);
        $bin1 = Bin::create([
            'rack_id' => $rack->id,
            'code' => 'B-01',
            'capacity_units' => $binCapacity,
            'is_pick_face' => true,
            'is_active' => true,
        ]);
        $bin2 = Bin::create([
            'rack_id' => $rack->id,
            'code' => 'B-02',
            'capacity_units' => $binCapacity,
            'is_pick_face' => false,
            'is_active' => true,
        ]);

        return [$wh, $zone, $bin1, $bin2];
    }

    public function test_executes_directed_putaway_into_target_bin_respecting_capacity(): void
    {
        [$wh, $zone, $bin1] = $this->createWarehouseStructure(50);
        $action = app(ExecuteDirectedPutawayAction::class);

        $result = $action->execute(
            productId: 1,
            qty: 25,
            preferredBin: $bin1,
            targetZone: $zone
        );

        $this->assertInstanceOf(BinStock::class, $result['stock']);
        $this->assertEquals($bin1->id, $result['stock']->bin_id);
        $this->assertEquals(25.0, (float) $result['stock']->qty);
        $this->assertEquals('done', $result['task']->status);
    }

    public function test_auto_directs_putaway_to_alternative_zone_bin_when_target_bin_is_full(): void
    {
        [$wh, $zone, $bin1, $bin2] = $this->createWarehouseStructure(20);
        // Fill bin1 to capacity
        BinStock::create([
            'bin_id' => $bin1->id,
            'product_id' => 1,
            'qty' => 20,
            'status' => 'available',
        ]);

        $action = app(ExecuteDirectedPutawayAction::class);
        $result = $action->execute(
            productId: 1,
            qty: 15,
            preferredBin: $bin1,
            targetZone: $zone
        );

        $this->assertEquals($bin2->id, $result['stock']->bin_id);
        $this->assertEquals(15.0, (float) $result['stock']->qty);
    }

    public function test_rejects_putaway_when_quantity_is_non_positive_or_all_bins_full(): void
    {
        [$wh, $zone, $bin1, $bin2] = $this->createWarehouseStructure(10);
        BinStock::create(['bin_id' => $bin1->id, 'product_id' => 1, 'qty' => 10, 'status' => 'available']);
        BinStock::create(['bin_id' => $bin2->id, 'product_id' => 1, 'qty' => 10, 'status' => 'available']);

        $action = app(ExecuteDirectedPutawayAction::class);

        $this->expectException(InvalidArgumentException::class);
        $action->execute(productId: 1, qty: 5, preferredBin: $bin1, targetZone: $zone);
    }

    public function test_allocates_outbound_wave_picking_tasks_across_available_inventory(): void
    {
        [$wh, $zone, $bin1] = $this->createWarehouseStructure(100);
        BinStock::create([
            'bin_id' => $bin1->id,
            'product_id' => 1,
            'qty' => 50,
            'status' => 'available',
        ]);

        $action = app(AllocateOutboundWavePickingAction::class);
        $result = $action->execute('fifo', [
            ['product_id' => 1, 'qty' => 20],
        ]);

        $this->assertEquals('released', $result['wave']->status);
        $this->assertCount(1, $result['tasks']);
        $this->assertEquals(20, $result['tasks'][0]->qty);
    }

    public function test_rejects_wave_allocation_when_requested_quantity_exceeds_available_stock(): void
    {
        [$wh, $zone, $bin1] = $this->createWarehouseStructure(100);
        BinStock::create(['bin_id' => $bin1->id, 'product_id' => 1, 'qty' => 5, 'status' => 'available']);

        $action = app(AllocateOutboundWavePickingAction::class);

        $this->expectException(InvalidArgumentException::class);
        $action->execute('fifo', [
            ['product_id' => 1, 'qty' => 50],
        ]);
    }

    public function test_processes_cross_docking_transfer_directly_from_dock_appointment(): void
    {
        [$originWh, $originZone, $bin1] = $this->createWarehouseStructure(100);
        [$destWh] = $this->createWarehouseStructure(100);

        // Seed stock at origin
        BinStock::create([
            'bin_id' => $bin1->id,
            'product_id' => 1,
            'qty' => 40,
            'status' => 'available',
        ]);

        $dock = DockAppointment::create([
            'warehouse_id' => $originWh->id,
            'dock_door' => 'D-01',
            'window_start' => now()->startOfHour(),
            'window_end' => now()->startOfHour()->addHours(2),
            'direction' => 'inbound',
            'reference' => 'INB-TEST-001',
            'status' => 'scheduled',
        ]);

        $action = app(ProcessCrossDockingTransferAction::class);
        $transfer = $action->execute($dock, $originWh, $destWh, [
            ['product_id' => 1, 'qty' => 10],
        ]);

        $this->assertEquals('in_transit', $transfer->status);
        $this->assertTrue((bool) $transfer->cross_dock);
    }

    public function test_rejects_cross_dock_when_inbound_dock_appointment_is_cancelled(): void
    {
        [$originWh] = $this->createWarehouseStructure(100);
        [$destWh] = $this->createWarehouseStructure(100);

        $dock = DockAppointment::create([
            'warehouse_id' => $originWh->id,
            'dock_door' => 'D-02',
            'window_start' => now()->startOfHour(),
            'window_end' => now()->startOfHour()->addHours(2),
            'direction' => 'inbound',
            'reference' => 'INB-TEST-002',
            'status' => 'cancelled',
        ]);

        $action = app(ProcessCrossDockingTransferAction::class);

        $this->expectException(InvalidArgumentException::class);
        $action->execute($dock, $originWh, $destWh, [
            ['product_id' => 1, 'qty' => 5],
        ]);
    }

    public function test_records_cycle_count_adjustment_and_enforces_four_eyes_approval(): void
    {
        [$wh, $zone, $bin1] = $this->createWarehouseStructure(100);
        BinStock::create(['bin_id' => $bin1->id, 'product_id' => 1, 'qty' => 50, 'status' => 'available']);

        $action = app(RecordCycleCountAdjustmentAction::class);
        $count = $action->execute(
            warehouse: $wh,
            lines: [
                ['bin_id' => $bin1->id, 'product_id' => 1, 'counted_qty' => 45.0],
            ],
            counter: $this->operator,
            approver: $this->admin
        );

        $this->assertEquals('applied', $count->status);
        $this->assertEquals(-5.0, (float) $count->variance_qty);
        $this->assertEquals(45.0, (float) BinStock::where('bin_id', $bin1->id)->sum('qty'));
    }

    public function test_rejects_cycle_count_approval_when_counter_is_identical_to_approver(): void
    {
        [$wh, $zone, $bin1] = $this->createWarehouseStructure(100);
        $action = app(RecordCycleCountAdjustmentAction::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prinsip empat mata');

        $action->execute(
            warehouse: $wh,
            lines: [
                ['bin_id' => $bin1->id, 'product_id' => 1, 'counted_qty' => 50.0],
            ],
            counter: $this->admin,
            approver: $this->admin
        );
    }

    public function test_calculates_3pl_storage_and_handling_billing_accurately(): void
    {
        [$wh] = $this->createWarehouseStructure(100);
        $action = app(Calculate3PlStorageHandlingBillingAction::class);

        $result = $action->execute($wh, 999, [
            'pallet_count' => 10,
            'storage_days' => 30,
            'storage_rate_per_day_idr' => 15_000,
            'inbound_units' => 200,
            'handling_in_rate_idr' => 2_500,
            'outbound_units' => 150,
            'handling_out_rate_idr' => 3_000,
            'vas_fee_idr' => 50_000,
        ]);

        $this->assertEquals(4_500_000, $result['storage_cost_idr']);
        $this->assertEquals(500_000, $result['handling_in_cost_idr']);
        $this->assertEquals(450_000, $result['handling_out_cost_idr']);
        $this->assertEquals(50_000, $result['vas_cost_idr']);
        $this->assertEquals(5_500_000, $result['subtotal_idr']);
        $this->assertEquals(605_000, $result['tax_idr']);
        $this->assertEquals(6_105_000, $result['total_billing_idr']);
    }
}
