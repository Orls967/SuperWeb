<?php

declare(strict_types=1);

namespace Modules\ControlTower\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ControlTower\Application\Services\ControlTowerService;
use Tests\TestCase;

class ControlTowerTest extends TestCase
{
    use RefreshDatabase;

    protected ControlTowerService $towerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->towerService = app(ControlTowerService::class);
    }

    public function test_53_1_and_53_5_echelon_stock_registration_and_abc_xyz(): void
    {
        $stock = $this->towerService->recordEchelonStock(
            itemCode: 'BRK-PAD-001',
            itemName: 'Ceramic Brake Pad Set',
            node: 'DC',
            onHand: 1500,
            inTransit: 500,
            reserved: 300,
            safetyStock: 400,
            abc: 'A',
            xyz: 'X'
        );

        $this->assertDatabaseHas('sct_echelon_stocks', [
            'item_code' => 'BRK-PAD-001',
            'echelon_node' => 'DC',
            'on_hand_qty' => 1500,
            'reserved_qty' => 300,
            'abc_class' => 'A',
            'xyz_class' => 'X',
        ]);
    }

    public function test_53_2_demand_forecasting_and_mape_evaluation(): void
    {
        $fc = $this->towerService->generateDemandForecast(
            itemCode: 'OIL-FLT-099',
            period: '2026-10',
            model: 'EXP_SMOOTHING',
            forecastQty: 1000
        );

        $this->assertDatabaseHas('sct_demand_forecasts', [
            'id' => $fc->id,
            'item_code' => 'OIL-FLT-099',
            'forecast_qty' => 1000,
            'actual_qty' => null,
        ]);

        // Record actual 950 -> error = 50 -> MAPE = 50 / 950 * 100 = 5.26%
        $fc = $this->towerService->recordActualDemandAndEvaluate($fc, 950);
        $this->assertEquals(950, $fc->actual_qty);
        $this->assertEquals(5.26, $fc->mape_percent);
    }

    public function test_53_4_order_promise_atp_and_ctp(): void
    {
        // Setup DC stock: 800 on hand, 300 reserved -> Free stock = 500
        $this->towerService->recordEchelonStock(
            itemCode: 'TIRE-R17-01',
            itemName: 'Sport Radial Tire 17"',
            node: 'DC',
            onHand: 800,
            inTransit: 0,
            reserved: 300,
            safetyStock: 100
        );

        // Customer requests 750 units
        // ATP should take 500 units from free stock, remaining 250 assigned to CTP manufacturing
        $promise = $this->towerService->calculatePromise(
            orderRef: 'ORD-SO-9901',
            itemCode: 'TIRE-R17-01',
            requestedQty: 750,
            targetDeliveryDate: '2026-10-20'
        );

        $this->assertDatabaseHas('sct_order_promises', [
            'id' => $promise->id,
            'order_reference' => 'ORD-SO-9901',
            'requested_qty' => 750,
            'atp_confirmed_qty' => 500,
            'ctp_manufacturing_qty' => 250,
            'promise_status' => 'confirmed',
        ]);
    }

    public function test_53_6_disruption_alert_trigger(): void
    {
        $alert = $this->towerService->triggerDisruptionAlert(
            severity: 'CRITICAL',
            category: 'PORT_CONGESTION',
            title: 'Tanjung Priok Berth Congestion',
            description: 'Vessel delayed by 5 days affecting raw material shipments',
            affectedOrdersCount: 42
        );

        $this->assertDatabaseHas('sct_disruption_alerts', [
            'id' => $alert->id,
            'severity' => 'CRITICAL',
            'affected_orders_count' => 42,
            'status' => 'active',
        ]);
    }

    public function test_53_9_control_tower_audit_and_command(): void
    {
        $this->towerService->recordEchelonStock(
            itemCode: 'CHIP-ECU-01',
            itemName: 'ECU Microcontroller',
            node: 'PLANT',
            onHand: 1000,
            inTransit: 200,
            reserved: 100,
            safetyStock: 50
        );

        $audit = $this->towerService->auditControlTower();
        $this->assertEquals('OK', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
        $this->assertGreaterThanOrEqual(1, $audit['echelon_count']);

        $this->artisan('tower:audit')
            ->expectsOutputToContain('Supply Chain Control Tower audit PASSED with 0 discrepancy.')
            ->assertExitCode(0);
    }

    public function test_53_10_http_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('control_tower.index'))
            ->assertOk()
            ->assertSee('Supply Chain Control Tower');

        $this->actingAs($user)
            ->get(route('control_tower.promises'))
            ->assertOk()
            ->assertSee('Janji Pesanan Konsumen');
    }
}
