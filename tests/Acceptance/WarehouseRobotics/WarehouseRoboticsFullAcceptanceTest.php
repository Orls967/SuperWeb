<?php

declare(strict_types=1);

use App\Models\User;
use App\Quality\Audit\Fixtures\RoboticsAuditCorruptionFixture;
use Modules\Core\Domain\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Wms\Application\Actions\AllocateRobotMissionsAction;
use Modules\Wms\Application\Actions\CompleteRobotMissionAction;
use Modules\Wms\Application\Actions\EvaluateRoboticsRoiProposalAction;
use Modules\Wms\Application\Actions\IngestSimulatedTelemetryBatchAction;
use Modules\Wms\Application\Actions\PlanRoboticsWaveAction;
use Modules\Wms\Application\Actions\ReserveZoneForMissionAction;
use Modules\Wms\Application\Actions\RetryOrFallbackRobotMissionAction;
use Modules\Wms\Application\Services\RoboticsMissionLifecycleSaga;
use Modules\Wms\Application\Services\RoboticsSimulationScenarioRunner;
use Modules\Wms\Application\Services\RoboticsT1SimulationReportService;
use Modules\Wms\Application\Services\RoboticsTwoSourceAuditService;
use Modules\Wms\Application\Services\RobotMissionTransitionService;
use Modules\Wms\database\seeders\RoboticsT1Seeder;
use Modules\Wms\Domain\Exceptions\RobotMissionTransitionException;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotMissionEvent;
use Modules\Wms\Models\RobotUnit;
use Modules\Wms\Models\TrafficZone;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Models\ZoneReservation;

uses(Tests\TestCase::class, RefreshDatabase::class);

function makeRoleUser(string $role): User
{
    Role::firstOrCreate(['name' => $role], ['label' => ucfirst($role), 'is_system' => true]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

test('1. akses seluruh grup rute robotika (role berhak 200 vs role tak berhak 403)', function (): void {
    $this->seed(RoboticsT1Seeder::class);

    $admin = makeRoleUser('admin');
    $hubOp = makeRoleUser('hub_operator');
    $auditor = makeRoleUser('auditor');
    $logisticsAdmin = makeRoleUser('logistics_admin');
    $guest = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($hubOp)->get('/wms/robotics/missions')->assertOk();
    $this->actingAs($hubOp)->get('/wms/robotics/zones')->assertOk();
    $this->actingAs($hubOp)->get('/wms/robotics/waves')->assertOk();
    $this->actingAs($logisticsAdmin)->get('/wms/robotics/waves')->assertOk();
    $this->actingAs($auditor)->get('/wms/robotics/roi')->assertOk();
    $this->actingAs($admin)->get('/wms/robotics/simulations')->assertOk();
    $this->actingAs($hubOp)->get('/wms/robotics/charging')->assertOk();
    $this->actingAs($hubOp)->get('/wms/robotics/fleet')->assertOk();
    $this->actingAs($auditor)->get('/wms/robotics/incidents')->assertOk();

    // Role tak berhak harus ditolak 403
    $this->actingAs($guest)->get('/wms/robotics/missions')->assertForbidden();
    $this->actingAs($auditor)->get('/wms/robotics/zones')->assertForbidden();
    $this->actingAs($logisticsAdmin)->get('/wms/robotics/zones')->assertForbidden();
});

test('2. alokasi Hungarian, reservasi zona J1, dan interlock menolak misi pada zona diblok staf', function (): void {
    $wh = Warehouse::query()->create(['code' => 'WH-ACC-2', 'name' => 'WH Acc 2', 'kind' => 'fulfillment', 'is_active' => true]);
    $whId = (string) $wh->id;

    $unit = RobotUnit::query()->create(['robot_code' => 'U-2', 'warehouse_id' => $whId, 'kind' => 'amr', 'battery_pct' => 90, 'status' => 'idle', 'max_speed_mm_s' => 1800]);
    Robot::query()->create(['robot_code' => 'R-ONLINE', 'warehouse_id' => $whId, 'battery_pct' => 90, 'is_online' => true]);
    Robot::query()->create(['robot_code' => 'R-OFFLINE', 'warehouse_id' => $whId, 'battery_pct' => 90, 'is_online' => false]);

    $openZone = TrafficZone::query()->create(['warehouse_id' => $whId, 'zone_code' => 'Z-OPEN', 'capacity_k' => 1, 'staff_blocked' => false]);
    $blockedZone = TrafficZone::query()->create(['warehouse_id' => $whId, 'zone_code' => 'Z-BLOCK', 'capacity_k' => 1, 'staff_blocked' => true, 'block_start_min' => 0, 'block_end_min' => 30, 'block_reason' => 'staf_aktif']);

    $m1 = RobotMission::query()->create(['mission_code' => 'MIS-A1', 'warehouse_id' => $whId, 'robot_unit_id' => $unit->id, 'zone_code' => 'Z-OPEN', 'status' => 'queued', 'priority' => 90]);
    $m2 = RobotMission::query()->create(['mission_code' => 'MIS-A2', 'warehouse_id' => $whId, 'robot_unit_id' => $unit->id, 'zone_code' => 'Z-BLOCK', 'status' => 'queued', 'priority' => 50]);

    $alloc = app(AllocateRobotMissionsAction::class)->execute($whId, 'WIN-1');
    expect(count($alloc['allocated']))->toBe(1)
        ->and($alloc['unassigned_mission_ids'])->toBe([$m2->id]);

    $m1->refresh();
    expect($m1->status)->toBe(RobotMissionStateMachine::STATUS_ASSIGNED);

    // Reservasi J1 berhasil di zona terbuka
    $res = app(ReserveZoneForMissionAction::class)->reserveAndStartMoving($m1, $openZone, 4, 3);
    expect($res['mission']->status)->toBe(RobotMissionStateMachine::STATUS_MOVING)
        ->and([$res['start_min'], $res['end_min']])->toBe([4, 7]);

    // Interlock menolak misi menuju zona diblok staf
    $m2->update(['status' => 'assigned', 'robot_id' => $m1->robot_id]);
    expect(fn () => app(ReserveZoneForMissionAction::class)->reserveAndStartMoving($m2->fresh(), $blockedZone, 0, 5))
        ->toThrow(RobotMissionTransitionException::class);
});

test('3. wave J2 tanpa order hilang/ganda saat fallback manual serta ROI J3 persetujuan ApprovalEngine', function (): void {
    $wh = Warehouse::query()->create(['code' => 'WH-ACC-3', 'name' => 'WH Acc 3', 'kind' => 'fulfillment', 'is_active' => true]);
    $whId = (string) $wh->id;
    $unit = RobotUnit::query()->create(['robot_code' => 'U-3', 'warehouse_id' => $whId, 'kind' => 'amr', 'battery_pct' => 90, 'status' => 'idle', 'max_speed_mm_s' => 1800]);
    $creator = makeRoleUser('hub_operator');
    $approver = makeRoleUser('admin');

    $orders = [
        ['id' => 'O1', 'size' => 40, 'carrier' => 'JNE', 'cutoff' => 1700],
        ['id' => 'O2', 'size' => 35, 'carrier' => 'JNE', 'cutoff' => 1700],
        ['id' => 'O3', 'size' => 30, 'carrier' => 'JNE', 'cutoff' => 1700],
    ];
    $waveAction = app(PlanRoboticsWaveAction::class);
    $initial = $waveAction->execute($whId, 70, $orders, 'B1');
    expect(count($initial['waves']))->toBe(2);

    $mission = RobotMission::query()->create(['mission_code' => 'MIS-WF-1', 'warehouse_id' => $whId, 'robot_unit_id' => $unit->id, 'zone_code' => 'Z1', 'status' => 'failed', 'failure_reason' => 'fault']);
    $fb = app(RetryOrFallbackRobotMissionAction::class)->fallbackAndReplan($mission, $creator->id, 'fault', 70, $orders);
    expect($fb['mission']->status)->toBe(RobotMissionStateMachine::STATUS_FALLBACK_MANUAL)
        ->and($fb['replanned_waves_count'])->toBe(2);

    // ROI J3: pembuat != penyetuju, NPV negatif butuh alasan
    $roiAction = app(EvaluateRoboticsRoiProposalAction::class);
    $neg = $roiAction->evaluateAndStore($whId, 'PROP-NEG', 'High Rate', 1_000_000_000, [400_000_000, 400_000_000, 400_000_000, 400_000_000], 1, 4, $creator->id);
    expect($neg['proposal']->npv_minor)->toBe(-55_360_000);

    expect(fn () => $roiAction->approveProposal($neg['proposal'], $creator->id, 'self'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $roiAction->approveProposal($neg['proposal'], $approver->id, ''))
        ->toThrow(InvalidArgumentException::class);

    $approved = $roiAction->approveProposal($neg['proposal'], $approver->id, 'Strategis otomasi lintas shift');
    expect($approved->status)->toBe('approved');
});

test('4. SM RobotMission retry maks 2, kompensasi saga melepas reservasi, simulasi sandbox tanpa ubah produksi, dan IDOR', function (): void {
    $wh = Warehouse::query()->create(['code' => 'WH-ACC-4', 'name' => 'WH Acc 4', 'kind' => 'fulfillment', 'is_active' => true]);
    $whId = (string) $wh->id;
    $unit = RobotUnit::query()->create(['robot_code' => 'U-4', 'warehouse_id' => $whId, 'kind' => 'amr', 'battery_pct' => 90, 'status' => 'idle', 'max_speed_mm_s' => 1800]);
    $robot = Robot::query()->create(['robot_code' => 'R-4', 'warehouse_id' => $whId, 'battery_pct' => 95, 'is_online' => true]);
    $zone = TrafficZone::query()->create(['warehouse_id' => $whId, 'zone_code' => 'Z-4', 'capacity_k' => 1, 'staff_blocked' => false]);

    $opA = makeRoleUser('hub_operator');
    $opB = makeRoleUser('hub_operator');

    $mission = RobotMission::query()->create([
        'mission_code' => 'MIS-SAGA-4',
        'warehouse_id' => $whId,
        'robot_unit_id' => $unit->id,
        'robot_id' => $robot->id,
        'traffic_zone_id' => $zone->id,
        'zone_code' => 'Z-4',
        'status' => 'assigned',
        'assigned_operator_user_id' => $opA->id,
    ]);

    // IDOR: operator B tidak boleh fallback misi milik operator A
    expect($opB->can('fallback', $mission))->toBeFalse()
        ->and($opA->can('fallback', $mission))->toBeTrue();

    // Reservasi lalu gagal -> kompensasi melepaskan reservasi aktif
    app(ReserveZoneForMissionAction::class)->reserveAndStartMoving($mission, $zone, 0, 5, $opA->id);
    expect(ZoneReservation::query()->where('mission_id', $mission->id)->where('status', 'active')->count())->toBe(1);

    $ts = app(RobotMissionTransitionService::class);
    $mission = $ts->transition($mission->fresh(), RobotMissionStateMachine::STATUS_FAILED, $opA->id, 'LIDAR_LOST');
    expect(ZoneReservation::query()->where('mission_id', $mission->id)->where('status', 'active')->count())->toBe(0);

    // Retry maks 2 kali, ke-3 ditolak MAX_RETRY_EXCEEDED
    $retryAction = app(RetryOrFallbackRobotMissionAction::class);
    $mission = $retryAction->retry($mission, $opA->id); // retry 1 -> queued
    $mission = $ts->transition($mission, 'assigned', $opA->id, 'REALLOC', null, ['has_hungarian_allocation' => true, 'robot_online' => true, 'robot_battery_pct' => 95]);
    $mission = $ts->transition($mission, 'failed', $opA->id, 'ERR_2');
    $mission = $retryAction->retry($mission, $opA->id); // retry 2 -> queued
    $mission = $ts->transition($mission, 'assigned', $opA->id, 'REALLOC2', null, ['has_hungarian_allocation' => true, 'robot_online' => true, 'robot_battery_pct' => 95]);
    $mission = $ts->transition($mission, 'failed', $opA->id, 'ERR_3');

    expect(fn () => $retryAction->retry($mission, $opA->id))
        ->toThrow(RobotMissionTransitionException::class);
    expect(RobotMissionEvent::query()->where('mission_id', $mission->id)->count())->toBeGreaterThanOrEqual(6);

    // Simulasi sandbox tidak mengubah hash tabel produksi
    $sim = app(RoboticsSimulationScenarioRunner::class)->runScenario('two_robots_offline_one_zone_blocked');
    expect($sim['hash_identical'])->toBeTrue();
});

test('5. robotics:audit bersih deterministik exit 0 dan fixture korupsi exit non-0', function (): void {
    $this->seed(RoboticsT1Seeder::class);

    $auditService = app(RoboticsTwoSourceAuditService::class);
    $run1 = $auditService->runAudit();
    $run2 = $auditService->runAudit();
    expect($run1)->toBe($run2)
        ->and($run1['ok'])->toBeTrue();

    $whId = (string) TrafficZone::query()->value('warehouse_id');
    $telBatch = app(IngestSimulatedTelemetryBatchAction::class)->execute($whId, 'T1-BATCH-1', 279);
    expect($telBatch['robots_processed'])->toBeGreaterThanOrEqual(20);

    $t1Report = app(RoboticsT1SimulationReportService::class)->buildReport($whId);
    expect($t1Report['audit_status']['passed'])->toBeTrue()
        ->and($t1Report['roi_portfolio']['negative_npv_count'])->toBeGreaterThanOrEqual(1);

    $exitClean = Artisan::call('robotics:audit');
    expect($exitClean)->toBe(0);

    $corruptedCode = app(RoboticsAuditCorruptionFixture::class)->corrupt();
    $exitDirty = Artisan::call('robotics:audit');
    $outDirty = Artisan::output();

    expect($exitDirty)->not->toBe(0)
        ->and($outDirty)->toContain('MIS-CORRUPT-01');
});
