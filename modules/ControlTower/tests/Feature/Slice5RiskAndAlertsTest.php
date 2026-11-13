<?php

declare(strict_types=1);

namespace Modules\ControlTower\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Modules\ControlTower\Application\Actions\ExecuteAutonomousReallocationPlaybookAction;
use Modules\ControlTower\Application\Services\AlertScannerService;
use Modules\ControlTower\Contracts\ItemAvailabilityReader;
use Modules\ControlTower\Domain\Models\AlertRule;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\ControlTower\Domain\Models\OrderRiskScore;
use Modules\ControlTower\Domain\Services\OrderRiskScorer;
use Modules\Core\Domain\Models\CoreEventSchema;
use Modules\Core\Domain\Models\OutboxMessage;
use Modules\Logistics\Contracts\InTransitShipmentProvider;
use Modules\Procurement\Contracts\SupplierLeadTimeStatistics;
use Tests\TestCase;

class Slice5RiskAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_risk_scorer_is_deterministic_across_all_four_factors(): void
    {
        $scorer = app(OrderRiskScorer::class);

        $low = $scorer->score(
            itemCode: 'SKU-LOW',
            requestedQty: 100,
            availableSupplyQty: 150,
            safetyStockQty: 50,
            leadTimeSigmaDays: 0,
            onTimePermil: 1000,
            leadTimeSamples: 10,
            oldestLotAgeDays: 0,
            onHandQty: 150,
            quarantineQty: 0,
            holdQty: 0,
            overdueShipmentsCount: 0,
            maxHoursInTransit: 0,
        );

        $this->assertSame(0, $low['risk_score']);
        $this->assertSame('LOW', $low['risk_tier']);
        $this->assertSame(0, $low['shortfall_component']);
        $this->assertSame(0, $low['lead_time_component']);
        $this->assertSame(0, $low['shelf_life_component']);
        $this->assertSame(0, $low['transit_component']);

        $critical = $scorer->score(
            itemCode: 'SKU-CRIT',
            requestedQty: 100,
            availableSupplyQty: 0,
            safetyStockQty: 50,
            leadTimeSigmaDays: 10,
            onTimePermil: 0,
            leadTimeSamples: 12,
            oldestLotAgeDays: 180,
            onHandQty: 100,
            quarantineQty: 50,
            holdQty: 0,
            overdueShipmentsCount: 3,
            maxHoursInTransit: 144,
        );

        $this->assertSame(1000, $critical['risk_score']);
        $this->assertSame('CRITICAL', $critical['risk_tier']);
        $this->assertSame(400, $critical['shortfall_component']);
        $this->assertSame(250, $critical['lead_time_component']);
        $this->assertSame(200, $critical['shelf_life_component']);
        $this->assertSame(150, $critical['transit_component']);
        $this->assertSame(1000, $critical['factors_json']['total_score']);
        $this->assertNotEmpty($critical['mitigation_plan']);
    }

    public function test_order_risk_assessment_service_and_artisan_command_with_contracts(): void
    {
        EchelonStock::create([
            'item_code' => 'BOLT-M12',
            'item_name' => 'Hex Bolt M12',
            'echelon_node' => 'PLANT',
            'on_hand_qty' => 20,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 100,
        ]);

        EchelonStock::create([
            'item_code' => 'NUT-M12',
            'item_name' => 'Hex Nut M12',
            'echelon_node' => 'DC',
            'on_hand_qty' => 500,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 100,
        ]);

        OrderPromise::create([
            'promise_code' => 'PRM-BOLT-01',
            'order_reference' => 'SO-2026-001',
            'item_code' => 'BOLT-M12',
            'requested_qty' => 100,
            'atp_confirmed_qty' => 20,
            'ctp_manufacturing_qty' => 0,
            'promised_delivery_date' => '2026-11-20',
            'promise_status' => 'partial',
        ]);

        $leadTimeMock = Mockery::mock(SupplierLeadTimeStatistics::class);
        $leadTimeMock->shouldReceive('primarySupplierForItem')->with('BOLT-M12')->andReturn('SUP-001');
        $leadTimeMock->shouldReceive('forSupplierAndItem')->with('SUP-001', 'BOLT-M12')->andReturn([
            'mean_days' => 7,
            'sigma_days' => 6,
            'samples' => 8,
            'on_time_permil' => 600,
        ]);
        $this->app->instance(SupplierLeadTimeStatistics::class, $leadTimeMock);

        $availabilityMock = Mockery::mock(ItemAvailabilityReader::class);
        $availabilityMock->shouldReceive('positionFor')->with('BOLT-M12', Mockery::type('string'))->andReturn([
            'on_hand' => 20,
            'available' => 20,
            'quarantine' => 0,
            'hold' => 0,
            'oldest_lot_age_days' => 90,
        ]);
        $this->app->instance(ItemAvailabilityReader::class, $availabilityMock);

        $transitMock = Mockery::mock(InTransitShipmentProvider::class);
        $transitMock->shouldReceive('getOverdueInTransitShipments')->with(72)->andReturn([
            [
                'shipment_id' => 91,
                'tracking_number' => 'TRK-OVERDUE-91',
                'origin_code' => 'WH-SBY',
                'destination_code' => 'WH-JKT',
                'hours_in_transit' => 108,
            ],
        ]);
        $this->app->instance(InTransitShipmentProvider::class, $transitMock);

        $this->artisan('tower:risk-scan', ['--item' => 'BOLT-M12'])
            ->assertExitCode(0);

        $score = OrderRiskScore::query()->where('item_code', 'BOLT-M12')->first();
        $this->assertNotNull($score);
        $this->assertSame('SO-2026-001', $score->order_reference);
        $this->assertSame(320, $score->shortfall_component);
        $this->assertSame(125, $score->lead_time_component);
        $this->assertSame(100, $score->shelf_life_component);
        $this->assertSame(60, $score->transit_component);
        $this->assertSame(605, $score->risk_score);
        $this->assertSame('HIGH', $score->risk_tier);
        $this->assertNull(OrderRiskScore::query()->where('item_code', 'NUT-M12')->first());
    }

    public function test_alert_scanner_is_idempotent_emits_outbox_and_registers_schema(): void
    {
        EchelonStock::create([
            'item_code' => 'PUMP-01',
            'item_name' => 'Hydraulic Pump',
            'echelon_node' => 'DC',
            'on_hand_qty' => 300,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 50,
        ]);

        EchelonStock::create([
            'item_code' => 'PUMP-01',
            'item_name' => 'Hydraulic Pump',
            'echelon_node' => 'PLANT',
            'on_hand_qty' => 0,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 40,
        ]);

        OrderRiskScore::create([
            'item_code' => 'PUMP-01',
            'order_reference' => 'SO-PUMP-99',
            'risk_score' => 820,
            'risk_tier' => 'CRITICAL',
            'shortfall_component' => 400,
            'lead_time_component' => 200,
            'shelf_life_component' => 120,
            'transit_component' => 100,
            'factors_json' => ['total_score' => 820],
            'mitigation_recommendation' => 'Segera realokasi dari DC.',
        ]);

        $transitMock = Mockery::mock(InTransitShipmentProvider::class);
        $transitMock->shouldReceive('getOverdueInTransitShipments')->with(72)->andReturn([
            [
                'shipment_id' => 501,
                'tracking_number' => 'TRK-501',
                'origin_code' => 'DC',
                'destination_code' => 'PLANT',
                'hours_in_transit' => 150,
            ],
        ]);
        $this->app->instance(InTransitShipmentProvider::class, $transitMock);

        // First scan
        $this->artisan('tower:alerts-scan')->assertExitCode(0);

        $this->assertSame(3, DisruptionAlert::query()->count());
        $this->assertSame(3, OutboxMessage::query()->where('event_type', AlertScannerService::EVENT_ALERT_RAISED)->count());
        $this->assertTrue(CoreEventSchema::query()->where('event_name', AlertScannerService::EVENT_ALERT_RAISED)->where('version', 1)->exists());

        $echelonAlert = DisruptionAlert::query()->where('dedup_key', 'echelon:PUMP-01:PLANT')->first();
        $this->assertNotNull($echelonAlert);
        $this->assertSame('CRITICAL', $echelonAlert->severity);
        $this->assertSame('reallocation', $echelonAlert->mitigation_proposal_json['action'] ?? null);
        $this->assertSame('DC', $echelonAlert->mitigation_proposal_json['from_node'] ?? null);
        $this->assertSame('PLANT', $echelonAlert->mitigation_proposal_json['to_node'] ?? null);
        $this->assertSame(40, $echelonAlert->mitigation_proposal_json['transfer_qty'] ?? null);

        // Second scan must be idempotent
        $this->artisan('tower:alerts-scan')->assertExitCode(0);
        $this->assertSame(3, DisruptionAlert::query()->count());
        $this->assertSame(3, OutboxMessage::query()->where('event_type', AlertScannerService::EVENT_ALERT_RAISED)->count());

        // Inactive AlertRule skips category
        AlertRule::create([
            'rule_code' => 'RULE-TRANSIT-OFF',
            'category' => 'TRANSIT_DELAY',
            'severity' => 'HIGH',
            'threshold_bps' => 7200,
            'is_active' => false,
        ]);

        $scanner = app(AlertScannerService::class);
        $scanRes = $scanner->scan();
        $categories = array_map(static fn (DisruptionAlert $a): string => $a->category, $scanRes['alerts']);
        $this->assertNotContains('TRANSIT_DELAY', $categories);
    }

    public function test_autonomous_reallocation_proposal_and_planner_approval_workflow(): void
    {
        $planner = User::factory()->create(['role' => 'planner']);

        $dc = EchelonStock::create([
            'item_code' => 'CHIP-88',
            'item_name' => 'Controller Chip 88',
            'echelon_node' => 'DC',
            'on_hand_qty' => 250,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 50,
        ]);

        $plant = EchelonStock::create([
            'item_code' => 'CHIP-88',
            'item_name' => 'Controller Chip 88',
            'echelon_node' => 'PLANT',
            'on_hand_qty' => 10,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 60,
        ]);

        $alert = DisruptionAlert::create([
            'alert_code' => 'ALT-CHIP-88-PLANT',
            'dedup_key' => 'echelon:CHIP-88:PLANT',
            'severity' => 'HIGH',
            'category' => 'SUPPLY_CHAIN_BOTTLENECK',
            'title' => 'Stockout Risk on CHIP-88 at PLANT',
            'description' => 'Low stock at PLANT',
            'affected_orders_count' => 5,
            'status' => 'active',
        ]);

        $action = app(ExecuteAutonomousReallocationPlaybookAction::class);

        $proposal = $action->proposeReallocation('CHIP-88', 'DC', 'PLANT', 50, $alert);
        $this->assertSame('pending_approval', $proposal['status']);
        // Stock must NOT move before approval
        $this->assertSame(250, $dc->fresh()->on_hand_qty);
        $this->assertSame(10, $plant->fresh()->on_hand_qty);

        // Approve proposal
        $approved = $action->approveReallocation($alert->fresh(), $planner->id);
        $this->assertSame(200, $approved['from_node_remaining_on_hand']);
        $this->assertSame(60, $approved['to_node_new_on_hand']);
        $this->assertSame('mitigated', $approved['status']);
        $this->assertSame($planner->id, $alert->fresh()->approved_by_user_id);
        $this->assertSame('approved', $alert->fresh()->mitigation_proposal_json['status'] ?? null);

        // Double approval must fail
        $this->expectException(InvalidArgumentException::class);
        $action->approveReallocation($alert->fresh(), $planner->id);
    }

    public function test_http_endpoints_role_guards_and_csv_exports(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);
        $planner = User::factory()->create(['role' => 'planner']);
        $viewer = User::factory()->create(['role' => 'customer']);

        EchelonStock::create([
            'item_code' => 'VALVE-99',
            'item_name' => 'Valve 99',
            'echelon_node' => 'DC',
            'on_hand_qty' => 200,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 50,
        ]);
        EchelonStock::create([
            'item_code' => 'VALVE-99',
            'item_name' => 'Valve 99',
            'echelon_node' => 'PLANT',
            'on_hand_qty' => 5,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 50,
        ]);

        // Unauthorized role cannot access GET
        $this->actingAs($viewer)->get(route('control_tower.risk.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('control_tower.alerts.index'))->assertForbidden();

        // Auditor can GET and export CSV, but cannot POST
        $this->actingAs($auditor)->get(route('control_tower.risk.index'))->assertOk();
        $this->actingAs($auditor)->get(route('control_tower.alerts.index'))->assertOk();
        $this->actingAs($auditor)->get(route('control_tower.risk.export'))->assertOk();
        $this->actingAs($auditor)->get(route('control_tower.alerts.export'))->assertOk();

        $this->actingAs($auditor)->post(route('control_tower.risk.scan'))->assertForbidden();
        $this->actingAs($auditor)->post(route('control_tower.alerts.scan'))->assertForbidden();

        // Planner can POST risk scan, alerts scan, and mitigate alert
        $this->actingAs($planner)->post(route('control_tower.risk.scan'))
            ->assertRedirect(route('control_tower.risk.index'));

        $this->assertGreaterThan(0, OrderRiskScore::query()->count());

        $this->actingAs($planner)->post(route('control_tower.alerts.scan'))
            ->assertRedirect(route('control_tower.alerts.index'));

        $alert = DisruptionAlert::query()->where('dedup_key', 'echelon:VALVE-99:PLANT')->firstOrFail();

        $this->actingAs($planner)->post(route('control_tower.alerts.mitigate', $alert->id))
            ->assertRedirect(route('control_tower.alerts.index'));

        $alert->refresh();
        $this->assertSame('mitigated', $alert->status);
        $this->assertSame($planner->id, $alert->approved_by_user_id);
        $this->assertSame(50, EchelonStock::query()->where('item_code', 'VALVE-99')->where('echelon_node', 'PLANT')->value('on_hand_qty'));

        // CSV downloads include expected header & content
        $riskCsv = $this->actingAs($auditor)->get(route('control_tower.risk.export'));
        $riskCsv->assertOk();
        $this->assertStringContainsString('item_code,order_reference,risk_score', $riskCsv->streamedContent());

        $alertCsv = $this->actingAs($auditor)->get(route('control_tower.alerts.export'));
        $alertCsv->assertOk();
        $this->assertStringContainsString('alert_code,dedup_key,severity', $alertCsv->streamedContent());
    }
}
