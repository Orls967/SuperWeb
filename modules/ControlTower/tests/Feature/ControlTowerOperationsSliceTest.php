<?php

declare(strict_types=1);

namespace Modules\ControlTower\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\ControlTower\Application\Actions\DetectSupplyChainBottleneckAlertAction;
use Modules\ControlTower\Application\Actions\ExecuteAutonomousReallocationPlaybookAction;
use Modules\ControlTower\Application\Actions\RunConsensusSopHoltWintersForecastAction;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Tests\TestCase;

class ControlTowerOperationsSliceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    public function test_run_consensus_sop_holt_winters_forecast_calculation(): void
    {
        $action = app(RunConsensusSopHoltWintersForecastAction::class);

        $res = $action->execute(
            'CHIP-MCU-32',
            '2026-11',
            [100, 110, 125, 140, 160],
            0.3,
            0.1
        );

        $this->assertNotNull($res['forecast']->id);
        $this->assertSame('HOLT_WINTERS', $res['algorithm_model']);
        $this->assertGreaterThan(120, $res['forecast_qty']);
        $this->assertSame('CHIP-MCU-32', $res['forecast']->item_code);
    }

    public function test_run_consensus_sop_holt_winters_forecast_validates_min_data_points(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $action = app(RunConsensusSopHoltWintersForecastAction::class);

        $action->execute('SKU-SHORT', '2026-11', [100, 120]);
    }

    public function test_detect_supply_chain_bottleneck_alert_critical_level(): void
    {
        // On hand 10, reserved 50 -> effective = -40 <= 0 -> CRITICAL
        EchelonStock::create([
            'item_code' => 'VALVE-OIL-01',
            'item_name' => 'High Pressure Oil Valve',
            'echelon_node' => 'PLANT',
            'on_hand_qty' => 10,
            'in_transit_qty' => 0,
            'reserved_qty' => 50,
            'safety_stock_qty' => 100,
            'abc_class' => 'A',
            'xyz_class' => 'X',
        ]);

        $action = app(DetectSupplyChainBottleneckAlertAction::class);
        $alerts = $action->execute('VALVE-OIL-01');

        $this->assertCount(1, $alerts);
        $this->assertSame('CRITICAL', $alerts[0]->severity);
        $this->assertSame('active', $alerts[0]->status);
    }

    public function test_detect_supply_chain_bottleneck_alert_high_level(): void
    {
        // Safety stock: 100. Effective stock: 40 (< 50, which is safety/2) -> HIGH
        EchelonStock::create([
            'item_code' => 'GEAR-PIN-02',
            'item_name' => 'Transmission Pinion',
            'echelon_node' => 'DC',
            'on_hand_qty' => 40,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 100,
            'abc_class' => 'B',
            'xyz_class' => 'Y',
        ]);

        $action = app(DetectSupplyChainBottleneckAlertAction::class);
        $alerts = $action->execute('GEAR-PIN-02');

        $this->assertCount(1, $alerts);
        $this->assertSame('HIGH', $alerts[0]->severity);
    }

    public function test_execute_autonomous_reallocation_playbook_success_and_alert_mitigation(): void
    {
        // Surplus at DC: on hand 1000
        EchelonStock::create([
            'item_code' => 'SENSOR-TPMS-05',
            'item_name' => 'TPMS Pressure Sensor',
            'echelon_node' => 'DC',
            'on_hand_qty' => 1000,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 200,
        ]);

        // Deficit at PLANT: on hand 20, safety 100
        EchelonStock::create([
            'item_code' => 'SENSOR-TPMS-05',
            'item_name' => 'TPMS Pressure Sensor',
            'echelon_node' => 'PLANT',
            'on_hand_qty' => 20,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 100,
        ]);

        // Existing active disruption alert at PLANT
        DisruptionAlert::create([
            'alert_code' => 'ALT-SENSOR-TPMS-05-PLANT',
            'severity' => 'HIGH',
            'category' => 'SUPPLY_CHAIN_BOTTLENECK',
            'title' => 'Stockout at PLANT',
            'description' => 'Stock low',
            'status' => 'active',
        ]);

        $action = app(ExecuteAutonomousReallocationPlaybookAction::class);
        $res = $action->execute('SENSOR-TPMS-05', 'DC', 'PLANT', 150);

        $this->assertSame(850, $res['from_node_remaining_on_hand']);
        $this->assertSame(170, $res['to_node_new_on_hand']);
        $this->assertSame('ALT-SENSOR-TPMS-05-PLANT', $res['mitigated_alert_code']);

        $alert = DisruptionAlert::where('alert_code', 'ALT-SENSOR-TPMS-05-PLANT')->first();
        $this->assertSame('mitigated', $alert->status);
    }

    public function test_control_tower_autonomous_controller_endpoints(): void
    {
        $this->actingAs($this->user);

        // 1. Cockpit Index
        $respDash = $this->get('/control-tower/autonomous');
        $respDash->assertOk();

        // 2. Forecast API
        $respFc = $this->postJson('/control-tower/autonomous/forecast', [
            'item_code' => 'PUMP-HYD-10',
            'period' => '2026-12',
            'historical_demands' => [300, 320, 310, 340, 360],
            'alpha' => 0.35,
            'beta' => 0.15,
        ]);
        $respFc->assertStatus(201);
        $this->assertArrayHasKey('forecast_qty', $respFc->json('data'));

        // 3. Bottlenecks API
        $respBot = $this->getJson('/control-tower/autonomous/bottlenecks');
        $respBot->assertOk();
        $this->assertTrue($respBot->json('success'));

        // 4. Reallocate API
        EchelonStock::create([
            'item_code' => 'TIRE-R18-RADIAL',
            'item_name' => '18 Inch Radial Tire',
            'echelon_node' => 'DC',
            'on_hand_qty' => 500,
            'safety_stock_qty' => 100,
        ]);

        $respRealloc = $this->postJson('/control-tower/autonomous/reallocate', [
            'item_code' => 'TIRE-R18-RADIAL',
            'from_node' => 'DC',
            'to_node' => 'OUTLET',
            'transfer_qty' => 80,
        ]);
        $respRealloc->assertOk();
        $this->assertSame(420, $respRealloc->json('data.from_node_remaining_on_hand'));
        $this->assertSame(80, $respRealloc->json('data.to_node_new_on_hand'));
    }
}
