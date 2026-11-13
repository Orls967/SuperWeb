<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Wms\Application\Actions\RechargeRobotUnitAction;
use Modules\Wms\Application\Services\RoboticsFleetOrchestratorService;
use Modules\Wms\Models\RobotUnit;

uses(RefreshDatabase::class);

function seedWh(): string
{
    DB::table('wms_warehouses')->insert([
        'id' => 'WH-ROB-FEAT',
        'code' => 'WH-ROB-FEAT',
        'name' => 'Gudang Feature Robotika',
        'kind' => 'dc',
        'city' => 'Jakarta',
        'is_active' => true,
        'created_at' => '2026-11-13 08:00:00',
        'updated_at' => '2026-11-13 08:00:00',
    ]);

    return 'WH-ROB-FEAT';
}

it('serves robotics fleet, missions, and roi pages and handles HTTP dispatch, interlock, and fallback', function (): void {
    $wh = seedWh();
    $operator = User::factory()->create(['role' => 'hub_operator']);

    $unit = RobotUnit::create([
        'robot_code' => 'AMR-FEAT-01',
        'warehouse_id' => $wh,
        'kind' => 'amr',
        'battery_pct' => 90,
        'status' => 'idle',
        'current_zone_code' => 'Z-F1',
        'max_speed_mm_s' => 1800,
    ]);

    $this->actingAs($operator)
        ->get(route('wms.robotics.fleet'))
        ->assertOk()
        ->assertSee('AMR-FEAT-01');

    $this->actingAs($operator)
        ->get(route('wms.robotics.missions'))
        ->assertOk();

    $this->actingAs($operator)
        ->get(route('wms.robotics.roi'))
        ->assertOk();

    $this->actingAs($operator)
        ->post(route('wms.robotics.missions.dispatch'), [
            'warehouse_id' => $wh,
            'robot_unit_id' => $unit->id,
            'zone_code' => 'Z-F1',
            'mission_code' => 'MIS-FEAT-01',
        ])
        ->assertRedirect(route('wms.robotics.missions'));

    $this->actingAs($operator)
        ->post(route('wms.robotics.interlock'), [
            'warehouse_id' => $wh,
            'zone_code' => 'Z-F1',
            'human_present' => true,
        ])
        ->assertRedirect(route('wms.robotics.fleet'));

    $missionId = (int) DB::table('wms_robot_missions')->where('mission_code', 'MIS-FEAT-01')->value('id');

    $this->actingAs($operator)
        ->post(route('wms.robotics.missions.fallback', ['mission' => $missionId]), [
            'reason' => 'operator_override',
        ])
        ->assertRedirect(route('wms.robotics.missions'));

    expect(DB::table('wms_robot_missions')->where('id', $missionId)->value('status'))->toBe('failed_fallback');
});

it('runs robotics CLI commands and recharges faulted robots cleanly', function (): void {
    $wh = seedWh();

    $unit = RobotUnit::create([
        'robot_code' => 'AMR-CLI-01',
        'warehouse_id' => $wh,
        'kind' => 'amr',
        'battery_pct' => 50,
        'status' => 'idle',
        'current_zone_code' => 'Z-C1',
        'max_speed_mm_s' => 1800,
    ]);

    $this->artisan('wms:robotics-roi-plan', [
        'warehouse_id' => $wh,
        'capex_idr' => 500_000_000,
        'annual_savings_idr' => 250_000_000,
    ])->assertExitCode(0);

    $this->artisan('wms:robotics-dispatch', [
        'warehouse_id' => $wh,
        'robot_unit_id' => $unit->id,
        'zone_code' => 'Z-C1',
        '--mission' => 'MIS-CLI-01',
    ])->assertExitCode(0);

    $this->artisan('wms:robotics-audit')->assertExitCode(0);

    $recharged = app(RechargeRobotUnitAction::class)->execute($unit->id, 98);
    expect($recharged->battery_pct)->toBe(98)
        ->and($recharged->status)->toBe('idle');

    $orchestration = app(RoboticsFleetOrchestratorService::class)->orchestrateWave(
        warehouseId: $wh,
        waveCode: 'WAVE-CLI-01',
        tasks: [
            ['task_code' => 'T-CLI-1', 'zone' => 'Z-C1', 'distance_m' => 8, 'priority' => 1],
        ],
    );
    expect($orchestration['wave_plan']['mode'])->toBe('automated')
        ->and($orchestration['deadlock']['has_deadlock'])->toBeFalse();
});

