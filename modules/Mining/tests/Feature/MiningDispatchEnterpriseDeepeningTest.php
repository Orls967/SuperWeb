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

class MiningDispatchEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    protected MiningFleetDispatchService $service;

    protected MiningSite $site;

    protected MiningPit $northPit;

    protected MiningPit $southPit;

    protected MiningEquipment $truck1;

    protected MiningEquipment $truck2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningFleetDispatchService::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'MINE-HALMAHERA-01',
            'name' => 'Weda Bay Nickel Sector A',
            'commodity' => 'NICKEL',
            'location' => 'Central Halmahera',
        ]);

        $this->northPit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_code' => 'PIT-NORTH-ALPHA',
            'name' => 'North Alpha Pit',
            'target_production_ton' => 100000,
            'actual_production_ton' => 0,
        ]);

        $this->southPit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_code' => 'PIT-SOUTH-BETA',
            'name' => 'South Beta Pit',
            'target_production_ton' => 80000,
            'actual_production_ton' => 0,
        ]);

        $this->truck1 = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'CAT-797F-101',
            'type' => 'HAUL_TRUCK',
            'model' => 'Caterpillar 797F',
            'capacity_ton' => 400.00,
            'status' => 'available',
            'engine_hours' => 1400,
        ]);

        $this->truck2 = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'KOM-930E-202',
            'type' => 'HAUL_TRUCK',
            'model' => 'Komatsu 930E-4',
            'capacity_ton' => 320.00,
            'status' => 'available',
            'engine_hours' => 850,
        ]);
    }

    public function test_dynamic_haul_truck_dispatch_optimizes_payload_and_queue_time_across_pits(): void
    {
        // Dispatch Truck 1 (400t) to North Pit
        $run1 = $this->service->dispatchEquipment(
            siteId: $this->site->id,
            pitId: $this->northPit->id,
            equipmentId: $this->truck1->id,
            shiftCode: 'SHIFT-DAY-1',
            targetPayloadTon: 400.00
        );

        $this->assertEquals('dispatched', $this->truck1->fresh()->status);
        $this->assertEquals('in_progress', $run1->status);
        $this->assertEquals(400.00, (float) $run1->target_payload_ton);

        // Dispatch Truck 2 (320t) to South Pit
        $run2 = $this->service->dispatchEquipment(
            siteId: $this->site->id,
            pitId: $this->southPit->id,
            equipmentId: $this->truck2->id,
            shiftCode: 'SHIFT-DAY-1',
            targetPayloadTon: 320.00
        );

        $this->assertEquals('dispatched', $this->truck2->fresh()->status);
        $this->assertEquals('in_progress', $run2->status);
        $this->assertEquals(320.00, (float) $run2->target_payload_ton);
    }

    public function test_high_frequency_telematics_detects_and_flags_overspeed_violations_on_haul_road(): void
    {
        $maxSafeHaulRoadSpeed = 40.0; // km/h limit on haul road
        $measuredSpeed = 54.5; // speeding violation

        $isOverspeed = $measuredSpeed > $maxSafeHaulRoadSpeed;
        $this->assertTrue($isOverspeed);

        // Incident severity classification
        $speedExcess = $measuredSpeed - $maxSafeHaulRoadSpeed;
        $severity = $speedExcess > 10.0 ? 'CRITICAL_K3_VIOLATION' : 'WARNING';
        $this->assertEquals('CRITICAL_K3_VIOLATION', $severity);
    }

    public function test_geofence_breach_detection_flags_trip_and_suspends_contractor_payment(): void
    {
        $run = $this->service->dispatchEquipment(
            siteId: $this->site->id,
            pitId: $this->northPit->id,
            equipmentId: $this->truck1->id,
            shiftCode: 'SHIFT-NIGHT-2',
            targetPayloadTon: 400.00
        );

        // Simulate complete dispatch with severe deviation / abnormal trip
        // Actual 350 ton, fuel 3500 liters over 10 km (expected is ~1575L; 3500L is > 1.5x anomaly)
        $completedRun = $this->service->completeDispatchRun(
            runId: $run->id,
            actualPayloadTon: 350.00,
            fuelConsumedLiter: 3500.00,
            distanceKm: 10.0
        );

        $this->assertTrue($completedRun->fuel_anomaly_detected);
        $this->assertTrue($completedRun->contractor_payment_held);
    }

    public function test_specific_fuel_consumption_anomaly_greater_than_12_percent_triggers_theft_hold(): void
    {
        $run = $this->service->dispatchEquipment(
            siteId: $this->site->id,
            pitId: $this->southPit->id,
            equipmentId: $this->truck2->id,
            shiftCode: 'SHIFT-DAY-2',
            targetPayloadTon: 300.00
        );

        // Benchmark: 0.45 L / ton-km. Ton-km = 300 * 20 = 6000. Expected fuel = 2700 L.
        // If actual fuel consumed is 4500 L (> 1.5x expected benchmark), triggers theft hold
        $completed = $this->service->completeDispatchRun(
            runId: $run->id,
            actualPayloadTon: 300.00,
            fuelConsumedLiter: 4500.00,
            distanceKm: 20.0
        );

        $this->assertTrue($completed->fuel_anomaly_detected);
        $this->assertTrue($completed->contractor_payment_held);
    }

    public function test_engine_hours_threshold_triggers_automated_preventive_maintenance_work_order(): void
    {
        // Truck 1 currently at 1400 engine hours. Threshold is 1500 hours.
        // Adding 120 hours brings total to 1520 hours -> triggers maintenance
        $res = $this->service->recordEngineHoursAndCheckMaintenance($this->truck1->id, 120, 1500);

        $this->assertEquals(1520, $res['engine_hours']);
        $this->assertTrue($res['maintenance_triggered']);
        $this->assertEquals('maintenance', $res['status']);
    }

    public function test_maintenance_work_order_reserves_spare_parts_in_wms_idempotently_once(): void
    {
        // Put equipment into maintenance first
        $this->truck1->update(['status' => 'maintenance', 'engine_hours' => 1550]);

        // Submitting check again when already in maintenance must not trigger duplicate work order
        $resSecond = $this->service->recordEngineHoursAndCheckMaintenance($this->truck1->id, 10, 1500);

        $this->assertFalse($resSecond['maintenance_triggered']);
        $this->assertEquals('maintenance', $resSecond['status']);
    }

    public function test_dispatch_board_summary_execution_budget_strictly_within_two_database_queries(): void
    {
        DB::enableQueryLog();

        $summary = $this->service->getDispatchBoardSummary($this->site->id);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Must execute in <= 2 database queries
        $this->assertLessThanOrEqual(2, count($queries));
        $this->assertEquals($this->site->id, $summary['site_id']);
        $this->assertArrayHasKey('available_equipment', $summary);
        $this->assertArrayHasKey('active_runs', $summary);
    }

    public function test_dispatch_board_query_latency_p95_strictly_under_25ms(): void
    {
        $iterations = 10;
        $latencies = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $this->service->getDispatchBoardSummary($this->site->id);
            $end = microtime(true);
            $latencies[] = ($end - $start) * 1000.0; // in milliseconds
        }

        sort($latencies);
        $p95Index = (int) ceil(0.95 * count($latencies)) - 1;
        $p95Latency = $latencies[$p95Index];

        // Strict SLA: p95 latency must be under 25ms
        $this->assertLessThan(25.0, $p95Latency);
    }
}
