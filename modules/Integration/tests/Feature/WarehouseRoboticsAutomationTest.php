<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\WarehouseRoboticsAutomationService;
use Tests\TestCase;

class WarehouseRoboticsAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected WarehouseRoboticsAutomationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WarehouseRoboticsAutomationService::class);
    }

    public function test_zone_reservation_and_collision_prevention(): void
    {
        // 1. Robot 1 reserves Zone A (279.2 & 279.4)
        $res = $this->service->reserveZone('AISLE_01_BAY_A', 'ROBOT_AMR_01');
        $this->assertTrue((bool) $res->is_reserved);
        $this->assertEquals('ROBOT_AMR_01', $res->active_robot_id);

        // 2. Robot 2 attempting to reserve same zone is blocked (collision guard) (279.4)
        try {
            $this->service->reserveZone('AISLE_01_BAY_A', 'ROBOT_AMR_02');
            $this->fail('Expected exception for zone collision conflict');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Collision prevention error', $e->getMessage());
        }

        // 3. Release zone allows subsequent reservation
        $this->service->releaseZone('AISLE_01_BAY_A');
        $res2 = $this->service->reserveZone('AISLE_01_BAY_A', 'ROBOT_AMR_02');
        $this->assertEquals('ROBOT_AMR_02', $res2->active_robot_id);
    }

    public function test_safety_interlock_blocks_entry_when_human_present(): void
    {
        // Human worker detected in aisle -> automated entry immediately blocked (279.6)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Safety interlock alert: Human worker present');
        $this->service->reserveZone('AISLE_PACKING_09', 'ROBOT_AMR_03', humanPresent: true);
    }

    public function test_wave_planning_feasibility_and_robot_failure_manual_fallback(): void
    {
        // 1. Wave planning within capacity limit (279.3 & 279.4)
        $this->service->registerRobot('AMR_FLEET_05', 'AMR_500KG');
        $wave = $this->service->planPickingWave('WAVE-2026-NIGHT-01', 'AMR_FLEET_05', 250, 300.0);
        $this->assertEquals('IN_PROGRESS', $wave->status);

        // 2. Overcapacity wave rejected (279.4)
        try {
            $this->service->planPickingWave('WAVE-OVERLOAD', 'AMR_FLEET_05', 500, 300.0);
            $this->fail('Expected exception for overcapacity wave');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Wave capacity feasibility error', $e->getMessage());
        }

        // 3. Robot failure mid-wave triggers automated manual fallback and wave replan (279.4 & 279.5 Edge Case)
        $fallbackWave = $this->service->handleRobotFailure('AMR_FLEET_05', 'WAVE-2026-NIGHT-01');
        $this->assertEquals('FALLBACK_MANUAL', $fallbackWave->status);
        $this->assertTrue((bool) $fallbackWave->is_re为其plann_required);

        $failedRobot = DB::table('warehouse_robot_fleet')->where('robot_code', 'AMR_FLEET_05')->first();
        $this->assertEquals('FAILED_DOWN', $failedRobot->status);
        $this->assertTrue((bool) $failedRobot->manual_fallback_active);
    }

    public function test_warehouse_automation_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerRobot('ROBOT-AUD', 'AMR');
        $this->service->reserveZone('ZONE-AUD', 'ROBOT-AUD');
        $this->service->planPickingWave('WAVE-AUD', 'ROBOT-AUD', 50, 100.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: failed robot without manual fallback flag
        DB::table('warehouse_robot_fleet')->insert([
            'robot_code' => 'ROBOT-BROKEN-SILENT',
            'model_type' => 'AGV',
            'battery_level_pct' => 0,
            'status' => 'FAILED_DOWN',
            'manual_fallback_active' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
