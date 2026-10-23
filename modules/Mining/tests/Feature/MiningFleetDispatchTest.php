<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Application\Services\MiningFleetDispatchService;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Tests\TestCase;

class MiningFleetDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected MiningFleetDispatchService $service;

    protected MiningSite $site;

    protected MiningPit $pit;

    protected MiningEquipment $truck;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningFleetDispatchService::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'MINE-MOROWALI-01',
            'name' => 'Morowali Nickel Project Block A',
            'commodity' => 'NICKEL',
            'location' => 'Central Sulawesi',
        ]);

        $this->pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_code' => 'PIT-NORTH-01',
            'name' => 'North Horizon Pit',
            'target_production_ton' => 50000,
            'actual_production_ton' => 0,
        ]);

        $this->truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'CAT-797F-001',
            'type' => 'HAUL_TRUCK',
            'model' => 'Caterpillar 797F 400-ton',
            'capacity_ton' => 400.00,
            'status' => 'available',
            'engine_hours' => 1200,
        ]);
    }

    public function test_equipment_dispatch_availability_and_assignment(): void
    {
        $run = $this->service->dispatchEquipment(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            equipmentId: $this->truck->id,
            shiftCode: 'SHIFT_DAY',
            targetPayloadTon: 380.00
        );

        $this->assertEquals('in_progress', $run->status);
        $this->assertEquals('dispatched', $this->truck->fresh()->status);

        // Cannot dispatch the same truck while dispatched
        $this->expectException(\RuntimeException::class);
        $this->service->dispatchEquipment(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            equipmentId: $this->truck->id,
            shiftCode: 'SHIFT_DAY',
            targetPayloadTon: 380.00
        );
    }

    public function test_complete_run_normal_fuel_and_payload_variance(): void
    {
        $run = $this->service->dispatchEquipment(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            equipmentId: $this->truck->id,
            shiftCode: 'SHIFT_DAY',
            targetPayloadTon: 380.00
        );

        // Actual 390 ton over 10 km = 3900 ton-km. Expected fuel ~ 3900 * 0.45 = 1755 L. Actual consumed 1800 L (normal)
        $completed = $this->service->completeDispatchRun(
            runId: $run->id,
            actualPayloadTon: 390.00,
            fuelConsumedLiter: 1800.00,
            distanceKm: 10.00
        );

        $this->assertEquals('completed', $completed->status);
        $this->assertEquals(10.00, (float) $completed->payload_variance_ton); // 390 - 380 = +10
        $this->assertFalse($completed->fuel_anomaly_detected);
        $this->assertFalse($completed->contractor_payment_held);
        $this->assertEquals('available', $this->truck->fresh()->status);
        $this->assertEquals(390, $this->pit->fresh()->actual_production_ton);
    }

    public function test_fuel_theft_anomaly_triggers_contractor_payment_hold(): void
    {
        $run = $this->service->dispatchEquipment(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            equipmentId: $this->truck->id,
            shiftCode: 'SHIFT_NIGHT',
            targetPayloadTon: 380.00
        );

        // 350 ton over 10 km = 3500 ton-km. Expected fuel ~ 1575 L. Actual consumed 4000 L (> 1.5x expected)
        $completed = $this->service->completeDispatchRun(
            runId: $run->id,
            actualPayloadTon: 350.00,
            fuelConsumedLiter: 4000.00,
            distanceKm: 10.00
        );

        $this->assertTrue($completed->fuel_anomaly_detected);
        $this->assertTrue($completed->contractor_payment_held);
    }

    public function test_c_engine_hours_above_threshold_triggers_work_order_once(): void
    {
        $this->truck->update(['engine_hours' => 1400, 'status' => 'available']);

        // 1. Adding 150 hours pushes to 1550 (>= 1500 threshold) -> triggers maintenance once
        $res1 = $this->service->recordEngineHoursAndCheckMaintenance($this->truck->id, 150, 1500);
        $this->assertTrue($res1['maintenance_triggered']);
        $this->assertEquals(1550, $res1['engine_hours']);
        $this->assertEquals('maintenance', $res1['status']);

        // 2. Adding more hours does not trigger duplicate maintenance (idempotent 1x)
        $res2 = $this->service->recordEngineHoursAndCheckMaintenance($this->truck->id, 50, 1500);
        $this->assertFalse($res2['maintenance_triggered']);
        $this->assertEquals(1600, $res2['engine_hours']);
        $this->assertEquals('maintenance', $res2['status']);
    }

    public function test_e_query_budget_dispatch_board_within_threshold(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $summary = $this->service->getDispatchBoardSummary($this->site->id);

        $queryCount = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(2, $queryCount);
        $this->assertEquals($this->site->id, $summary['site_id']);
        $this->assertArrayHasKey('available_equipment', $summary);
        $this->assertArrayHasKey('dispatched_equipment', $summary);
        $this->assertArrayHasKey('active_runs', $summary);
    }
}
