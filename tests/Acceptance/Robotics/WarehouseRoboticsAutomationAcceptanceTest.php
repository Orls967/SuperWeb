<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Wms\Application\Actions\CreateAutomationPlanAction;
use Modules\Wms\Application\Actions\DispatchRobotMissionAction;
use Modules\Wms\Application\Actions\EnforceZoneSafetyInterlockAction;
use Modules\Wms\Application\Actions\TriggerRobotFallbackAction;
use Modules\Wms\Application\Services\RoboticsAuditCorruptionFixture;
use Modules\Wms\Domain\Algorithms\AutomationRoiPlanner;
use Modules\Wms\Domain\Algorithms\RobotTaskAllocator;
use Modules\Wms\Domain\Algorithms\TrafficDeadlockDetector;
use Modules\Wms\Domain\Algorithms\WaveAutomationDispatcher;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\RoboticsPlan;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotUnit;
use Modules\Wms\Models\RobotZoneLock;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function seedRoboticsWarehouseAndUser(): array
{
    $user = User::factory()->create(['role' => 'hub_operator']);

    DB::table('wms_warehouses')->insert([
        'id' => 'WH-ROB-01',
        'code' => 'WH-ROB-01',
        'name' => 'Gudang Robotika Cikarang',
        'kind' => 'dc',
        'city' => 'Bekasi',
        'is_active' => true,
        'created_at' => '2026-11-13 08:00:00',
        'updated_at' => '2026-11-13 08:00:00',
    ]);

    return [$user, 'WH-ROB-01'];
}

it('executes end-to-end J1 ROI planning, J2 Hungarian allocation, J3 wave dispatch, safety interlock, and fallback manual', function (): void {
    [$user, $warehouseId] = seedRoboticsWarehouseAndUser();

    // 1. J1 AutomationRoiPlanner (integer-only bps & payback months)
    $planAction = app(CreateAutomationPlanAction::class);
    $planResult = $planAction->execute(
        warehouseId: $warehouseId,
        pickMethod: 'goods_to_person_amr',
        targetLinesPerHour: 420,
        capexIdr: 1_200_000_000,
        annualSavingsIdr: 480_000_000,
        phaseStage: 'phase_2_amr',
    );

    expect($planResult['plan'])->toBeInstanceOf(RoboticsPlan::class)
        ->and($planResult['certificate']->isValid())->toBeTrue()
        ->and($planResult['plan']->roi_bps)->toBe(4000)
        ->and($planResult['plan']->payback_months)->toBe(30);

    // 2. Register 2 AMR units
    $r1 = RobotUnit::create([
        'robot_code' => 'AMR-001',
        'warehouse_id' => $warehouseId,
        'kind' => 'amr',
        'battery_pct' => 92,
        'status' => 'idle',
        'current_zone_code' => 'Z-A1',
        'max_speed_mm_s' => 1800,
    ]);
    $r2 = RobotUnit::create([
        'robot_code' => 'AMR-002',
        'warehouse_id' => $warehouseId,
        'kind' => 'amr',
        'battery_pct' => 85,
        'status' => 'idle',
        'current_zone_code' => 'Z-B2',
        'max_speed_mm_s' => 1800,
    ]);

    // 3. J2 RobotTaskAllocator (reusing Shared::HungarianSolver)
    $allocator = app(RobotTaskAllocator::class);
    $allocation = $allocator->allocate(
        robots: [
            ['id' => $r1->id, 'code' => $r1->robot_code, 'zone' => 'Z-A1', 'battery_pct' => 92],
            ['id' => $r2->id, 'code' => $r2->robot_code, 'zone' => 'Z-B2', 'battery_pct' => 85],
        ],
        tasks: [
            ['task_code' => 'T-101', 'zone' => 'Z-A1', 'distance_m' => 10],
            ['task_code' => 'T-102', 'zone' => 'Z-B2', 'distance_m' => 12],
        ],
    );

    expect($allocation['certificate']->isValid())->toBeTrue()
        ->and($allocation['assignments'])->toHaveCount(2)
        ->and($allocation['assignments'][0]['robot_id'])->toBe($r1->id)
        ->and($allocation['assignments'][0]['task_code'])->toBe('T-101');

    // 4. J3 WaveAutomationDispatcher + DispatchRobotMissionAction + RobotMissionStateMachine
    $dispatcher = app(WaveAutomationDispatcher::class);
    $wavePlan = $dispatcher->planWaveDispatch(
        waveCode: 'WAVE-279-01',
        tasks: [
            ['task_code' => 'T-101', 'zone' => 'Z-A1', 'priority' => 1],
            ['task_code' => 'T-102', 'zone' => 'Z-B2', 'priority' => 2],
        ],
        availableRobotIds: [$r1->id, $r2->id],
    );

    expect($wavePlan['certificate']->isValid())->toBeTrue()
        ->and($wavePlan['mode'])->toBe('automated');

    $dispatchAction = app(DispatchRobotMissionAction::class);
    $mission = $dispatchAction->execute(
        warehouseId: $warehouseId,
        robotUnitId: $r1->id,
        zoneCode: 'Z-A1',
        missionCode: 'MIS-279-01',
        waveId: null,
        operatorUserId: $user->id,
    );

    expect($mission)->toBeInstanceOf(RobotMission::class)
        ->and($mission->status)->toBe(RobotMissionStateMachine::STATUS_MOVING);

    // 5. Safety Interlock: human enters Z-A1 -> robot halted (failed) & speed capped
    $interlockAction = app(EnforceZoneSafetyInterlockAction::class);
    $lock = $interlockAction->execute(
        warehouseId: $warehouseId,
        zoneCode: 'Z-A1',
        humanPresent: true,
    );

    expect($lock)->toBeInstanceOf(RobotZoneLock::class)
        ->and($lock->interlock_active)->toBeTrue()
        ->and($lock->speed_limit_mm_s)->toBe(300)
        ->and($mission->fresh()->status)->toBe(RobotMissionStateMachine::STATUS_FAILED);

    // 6. Robot failure -> TriggerRobotFallbackAction switches mission to manual fallback
    $fallbackAction = app(TriggerRobotFallbackAction::class);
    $fallbackMission = $fallbackAction->execute(
        missionId: $mission->id,
        operatorUserId: $user->id,
        reason: 'sensor_lidar_fault',
    );

    expect($fallbackMission->status)->toBe(RobotMissionStateMachine::STATUS_FALLBACK_MANUAL)
        ->and($fallbackMission->fallback_manual)->toBeTrue()
        ->and($fallbackMission->assigned_operator_user_id)->toBe($user->id);

    // 7. TrafficDeadlockDetector detects 2-robot cycle
    $detector = app(TrafficDeadlockDetector::class);
    $cycleResult = $detector->detectCycle([
        'AMR-001' => 'AMR-002',
        'AMR-002' => 'AMR-001',
    ]);

    expect($cycleResult['has_deadlock'])->toBeTrue()
        ->and($cycleResult['cycle_nodes'])->toContain('AMR-001', 'AMR-002');
});

it('fails wms:audit when RoboticsAuditCorruptionFixture injects dual active zone locks', function (): void {
    [$user, $warehouseId] = seedRoboticsWarehouseAndUser();

    $r1 = RobotUnit::create([
        'robot_code' => 'AMR-010',
        'warehouse_id' => $warehouseId,
        'kind' => 'amr',
        'battery_pct' => 90,
        'status' => 'active',
        'current_zone_code' => 'Z-X1',
        'max_speed_mm_s' => 1500,
    ]);
    $r2 = RobotUnit::create([
        'robot_code' => 'AMR-011',
        'warehouse_id' => $warehouseId,
        'kind' => 'amr',
        'battery_pct' => 88,
        'status' => 'active',
        'current_zone_code' => 'Z-X1',
        'max_speed_mm_s' => 1500,
    ]);

    app(RoboticsAuditCorruptionFixture::class)->injectDualActiveZoneLocks(
        warehouseId: $warehouseId,
        zoneCode: 'Z-X1',
        firstRobotId: $r1->id,
        secondRobotId: $r2->id,
    );

    $this->artisan('wms:audit')
        ->assertExitCode(1);
});
