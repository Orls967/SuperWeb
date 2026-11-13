<?php

declare(strict_types=1);

namespace Modules\ControlTower\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\ControlTower\Application\Services\ControlTowerKpiDrilldownService;
use Modules\ControlTower\Application\Services\TwinWhatIfOrchestratorService;
use Modules\ControlTower\Domain\Models\ControlTowerKpiSnapshot;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\ControlTower\Domain\Models\TwinScenario;
use Modules\Core\Domain\Models\SimRun;
use Modules\Distribution\Domain\Models\DistOrder;
use Modules\Distribution\Domain\Models\DistOrderLine;
use Modules\Distribution\Domain\Models\Distributor;
use Tests\TestCase;

class Slice6TwinAndKpiTest extends TestCase
{
    use RefreshDatabase;

    private User $planner;

    private User $auditor;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planner = User::factory()->create(['role' => 'planner']);
        $this->auditor = User::factory()->create(['role' => 'auditor']);
        $this->operator = User::factory()->create(['role' => 'operator']);
    }

    public function test_what_if_twin_runs_in_zero_mutation_sandbox_even_without_seeded_sim_run(): void
    {
        $this->assertSame(0, SimRun::query()->count());

        EchelonStock::create([
            'item_code' => 'SKU-TWIN-01',
            'item_name' => 'Industrial Servo Motor',
            'echelon_node' => 'DC',
            'on_hand_qty' => 500,
            'in_transit_qty' => 100,
            'reserved_qty' => 50,
            'safety_stock_qty' => 120,
            'abc_class' => 'A',
            'xyz_class' => 'X',
        ]);

        OrderPromise::create([
            'promise_code' => 'PRM-TWIN-001',
            'order_reference' => 'SO-TWIN-001',
            'item_code' => 'SKU-TWIN-01',
            'requested_qty' => 80,
            'atp_confirmed_qty' => 80,
            'ctp_manufacturing_qty' => 0,
            'promised_delivery_date' => '2026-11-20',
            'promise_status' => 'confirmed',
        ]);

        DisruptionAlert::create([
            'alert_code' => 'ALT-TWIN-001',
            'severity' => 'HIGH',
            'category' => 'PORT_CONGESTION',
            'title' => 'Port Queue Delay',
            'description' => 'Initial operational alert',
            'affected_orders_count' => 3,
            'status' => 'active',
        ]);

        /** @var TwinWhatIfOrchestratorService $orchestrator */
        $orchestrator = app(TwinWhatIfOrchestratorService::class);
        $expectedInitialHash = $orchestrator->computeOperationalStateHash();

        $portClosure = $orchestrator->runScenario(
            shockType: 'PORT_CLOSURE',
            shockMagnitudeBps: 2500,
            durationDays: 14,
            name: 'Penutupan Pelabuhan 14 Hari',
            actorUserId: $this->planner->id,
        );

        $this->assertTrue($portClosure['zero_mutation_verified']);
        $this->assertSame($expectedInitialHash, $portClosure['before_hash']);
        $this->assertSame($portClosure['before_hash'], $portClosure['after_hash']);
        $this->assertSame('PORT_CLOSURE', $portClosure['shock_type']);
        $this->assertLessThan($portClosure['baseline_otif_bps'], $portClosure['simulated_otif_bps']);
        $this->assertNotEmpty($portClosure['simulation_fingerprint']);
        $this->assertNotEmpty($portClosure['tick_samples']);

        $demandSpike = $orchestrator->runScenario(
            shockType: 'DEMAND_SPIKE',
            shockMagnitudeBps: 3000,
            durationDays: 10,
            name: 'Lonjakan Permintaan 30%',
            actorUserId: $this->planner->id,
        );

        $this->assertTrue($demandSpike['zero_mutation_verified']);
        $this->assertSame($expectedInitialHash, $demandSpike['before_hash']);
        $this->assertSame($demandSpike['before_hash'], $demandSpike['after_hash']);
        $this->assertSame('DEMAND_SPIKE', $demandSpike['shock_type']);

        // Only sct_twin_scenarios receives summary rows; operational tables remain identical
        $this->assertSame(2, TwinScenario::query()->count());
        $this->assertSame($expectedInitialHash, $orchestrator->computeOperationalStateHash());
    }

    public function test_what_if_supports_all_six_scenarios_and_side_by_side_comparison(): void
    {
        /** @var TwinWhatIfOrchestratorService $orchestrator */
        $orchestrator = app(TwinWhatIfOrchestratorService::class);

        $scenarios = [];
        foreach (TwinWhatIfOrchestratorService::SUPPORTED_SCENARIOS as $type) {
            $res = $orchestrator->runScenario(
                shockType: $type,
                shockMagnitudeBps: 2000,
                durationDays: 7,
                actorUserId: $this->planner->id,
            );
            $scenarios[$type] = $res;
            $this->assertSame($type, $res['shock_type']);
            $this->assertSame($res['before_hash'], $res['after_hash']);
        }

        $comparison = $orchestrator->compareScenarios(
            $scenarios['PORT_CLOSURE']['scenario_id'],
            $scenarios['MATERIAL_INFLATION']['scenario_id'],
        );

        $this->assertSame($scenarios['PORT_CLOSURE']['scenario_code'], $comparison['scenario_a']['scenario_code']);
        $this->assertSame($scenarios['MATERIAL_INFLATION']['scenario_code'], $comparison['scenario_b']['scenario_code']);
        $this->assertSame(
            $scenarios['MATERIAL_INFLATION']['simulated_otif_bps'] - $scenarios['PORT_CLOSURE']['simulated_otif_bps'],
            $comparison['delta']['simulated_otif_bps']
        );
        $this->assertSame($scenarios['MATERIAL_INFLATION']['scenario_code'], $comparison['better_otif_scenario']);
    }

    public function test_what_if_rejects_unsupported_scenario(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(TwinWhatIfOrchestratorService::class)->runScenario('ALIEN_INVASION', 1000, 5);
    }

    public function test_real_otif_and_fill_rate_permil_kpi_and_drilldown_from_dist_orders(): void
    {
        $distributor = Distributor::create([
            'code' => 'DIST-S6-01',
            'name' => 'PT Nusantara Distribusi',
            'kind' => 'distributor',
            'tier' => 'gold',
            'status' => 'approved',
            'payment_terms_days' => 30,
            'credit_limit_idr' => 500_000_000,
            'credit_exposure_idr' => 0,
        ]);

        // 1) ON_TIME: ordered 100, delivered 100, due 2026-11-10, delivered 2026-11-09
        $orderOnTime = DistOrder::create([
            'number' => 'DOR/S6/2026-00001',
            'distributor_id' => $distributor->id,
            'status' => 'delivered',
            'requested_date' => '2026-11-10',
            'delivered_at' => '2026-11-09 14:00:00',
        ]);
        DistOrderLine::create([
            'order_id' => $orderOnTime->id,
            'product_id' => 1,
            'sku' => 'SKU-ALPHA',
            'name_snapshot' => 'Alpha Valve',
            'qty' => 100,
            'allocated_qty' => 100,
            'shipped_qty' => 100,
            'unit_price_idr' => 10_000,
            'line_total_idr' => 1_000_000,
            'line_status' => 'delivered',
        ]);

        // 2) LATE: ordered 100, delivered 100, due 2026-11-10, delivered 2026-11-12
        $orderLate = DistOrder::create([
            'number' => 'DOR/S6/2026-00002',
            'distributor_id' => $distributor->id,
            'status' => 'delivered',
            'requested_date' => '2026-11-10',
            'delivered_at' => '2026-11-12 09:00:00',
        ]);
        DistOrderLine::create([
            'order_id' => $orderLate->id,
            'product_id' => 1,
            'sku' => 'SKU-ALPHA',
            'name_snapshot' => 'Alpha Valve',
            'qty' => 100,
            'allocated_qty' => 100,
            'shipped_qty' => 100,
            'unit_price_idr' => 10_000,
            'line_total_idr' => 1_000_000,
            'line_status' => 'delivered',
        ]);

        // 3) SHORT: ordered 100, delivered 60, due 2026-11-11, delivered 2026-11-11
        $orderShort = DistOrder::create([
            'number' => 'DOR/S6/2026-00003',
            'distributor_id' => $distributor->id,
            'status' => 'delivered',
            'requested_date' => '2026-11-11',
            'delivered_at' => '2026-11-11 10:00:00',
        ]);
        DistOrderLine::create([
            'order_id' => $orderShort->id,
            'product_id' => 2,
            'sku' => 'SKU-BETA',
            'name_snapshot' => 'Beta Pump',
            'qty' => 100,
            'allocated_qty' => 60,
            'shipped_qty' => 60,
            'unit_price_idr' => 20_000,
            'line_total_idr' => 2_000_000,
            'line_status' => 'delivered',
        ]);

        // 4) OPEN: ordered 100, delivered 0, due 2026-11-12, delivered_at null
        $orderOpen = DistOrder::create([
            'number' => 'DOR/S6/2026-00004',
            'distributor_id' => $distributor->id,
            'status' => 'allocated',
            'requested_date' => '2026-11-12',
            'delivered_at' => null,
        ]);
        DistOrderLine::create([
            'order_id' => $orderOpen->id,
            'product_id' => 2,
            'sku' => 'SKU-BETA',
            'name_snapshot' => 'Beta Pump',
            'qty' => 100,
            'allocated_qty' => 100,
            'shipped_qty' => 0,
            'unit_price_idr' => 20_000,
            'line_total_idr' => 2_000_000,
            'line_status' => 'allocated',
        ]);

        // Stockout item + non-stockout item + active alert
        EchelonStock::create([
            'item_code' => 'SKU-ALPHA',
            'item_name' => 'Alpha Valve',
            'echelon_node' => 'DC',
            'on_hand_qty' => 800,
            'in_transit_qty' => 0,
            'reserved_qty' => 100,
            'safety_stock_qty' => 100,
        ]);
        EchelonStock::create([
            'item_code' => 'SKU-BETA',
            'item_name' => 'Beta Pump',
            'echelon_node' => 'DC',
            'on_hand_qty' => 20,
            'in_transit_qty' => 0,
            'reserved_qty' => 20,
            'safety_stock_qty' => 50,
        ]);
        DisruptionAlert::create([
            'alert_code' => 'ALT-KPI-001',
            'severity' => 'CRITICAL',
            'category' => 'STOCKOUT',
            'title' => 'Beta Pump Stockout',
            'description' => 'Available stock zero',
            'affected_orders_count' => 1,
            'status' => 'active',
        ]);

        /** @var ControlTowerKpiDrilldownService $kpiService */
        $kpiService = app(ControlTowerKpiDrilldownService::class);
        $report = $kpiService->drilldown('2026-11-01', '2026-11-20');

        // 1 of 4 lines is ON_TIME -> 250 permil
        $this->assertSame(250, $report['metrics']['otif_permil']);
        // sum(min(delivered, ordered)) = 100 + 100 + 60 + 0 = 260 out of 400 -> 650 permil
        $this->assertSame(650, $report['metrics']['fill_rate_permil']);
        $this->assertSame(1, $report['metrics']['stockout_items_count']);
        $this->assertSame(1, $report['metrics']['active_alerts_count']);
        // 400 ordered across 20 days = 20/day; 820 on hand / 20 = 41 days DOS; C2C = 41 + 20 - 30 = 31
        $this->assertSame(41, $report['metrics']['inventory_dos_days']);
        $this->assertSame(31, $report['metrics']['cash_to_cash_days']);

        $this->assertCount(2, $report['drilldown']['per_sku']);
        $this->assertCount(4, $report['drilldown']['per_order']);

        $statusesByOrder = collect($report['drilldown']['per_order'])->pluck('status', 'order_ref')->all();
        $this->assertSame('ON_TIME', $statusesByOrder['DOR/S6/2026-00001']);
        $this->assertSame('LATE', $statusesByOrder['DOR/S6/2026-00002']);
        $this->assertSame('SHORT', $statusesByOrder['DOR/S6/2026-00003']);
        $this->assertSame('OPEN', $statusesByOrder['DOR/S6/2026-00004']);

        // Idempotent daily snapshot capture
        $snap1 = $kpiService->captureDailySnapshot('2026-11-01', '2026-11-20');
        $snap2 = $kpiService->captureDailySnapshot('2026-11-01', '2026-11-20');

        $this->assertSame($snap1['snapshot']->id, $snap2['snapshot']->id);
        $this->assertSame(1, ControlTowerKpiSnapshot::query()->count());
        $this->assertSame(2500, $snap2['snapshot']->otif_bps);
    }

    public function test_console_commands_tower_whatif_and_tower_kpi(): void
    {
        $this->artisan('tower:whatif', [
            'scenario' => 'SUPPLY_DELAY',
            '--magnitude' => 1500,
            '--days' => 10,
        ])
            ->expectsOutputToContain('zero_mutation=true')
            ->assertExitCode(0);

        $this->assertDatabaseHas('sct_twin_scenarios', [
            'shock_type' => 'SUPPLY_DELAY',
            'shock_magnitude_bps' => 1500,
            'duration_days' => 10,
        ]);

        $this->artisan('tower:kpi', [
            '--from' => '2026-11-01',
            '--to' => '2026-11-15',
        ])
            ->expectsOutputToContain('OTIF=')
            ->assertExitCode(0);

        $this->assertSame(1, ControlTowerKpiSnapshot::query()->count());
    }

    public function test_http_endpoints_rbac_and_csv_exports(): void
    {
        // Auditor cannot run what-if simulation (403)
        $this->actingAs($this->auditor)
            ->postJson(route('control_tower.twin.whatif'), [
                'shock_type' => 'PORT_CLOSURE',
                'shock_magnitude_bps' => 2500,
                'duration_days' => 14,
            ])
            ->assertForbidden();

        // Operator cannot access control tower endpoints (403)
        $this->actingAs($this->operator)
            ->getJson(route('control_tower.kpi.drilldown'))
            ->assertForbidden();

        // Planner can run what-if simulation
        $whatIfResp = $this->actingAs($this->planner)
            ->postJson(route('control_tower.twin.whatif'), [
                'name' => 'HTTP What-If Port Closure',
                'shock_type' => 'PORT_CLOSURE',
                'shock_magnitude_bps' => 2500,
                'duration_days' => 14,
            ])
            ->assertCreated();

        $this->assertTrue($whatIfResp->json('data.zero_mutation_verified'));
        $this->assertSame(
            $whatIfResp->json('data.before_hash'),
            $whatIfResp->json('data.after_hash')
        );

        // Auditor can view KPI drilldown and export CSVs
        $this->actingAs($this->auditor)
            ->getJson(route('control_tower.kpi.drilldown'))
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period' => ['from', 'to', 'days'],
                    'metrics' => [
                        'otif_permil',
                        'fill_rate_permil',
                        'stockout_items_count',
                        'active_alerts_count',
                        'inventory_dos_days',
                        'cash_to_cash_days',
                    ],
                    'drilldown' => ['per_sku', 'per_order', 'lines'],
                ],
            ]);

        $twinCsv = $this->actingAs($this->auditor)
            ->get(route('control_tower.twin.export'))
            ->assertOk();
        $this->assertStringContainsString('text/csv', (string) $twinCsv->headers->get('content-type'));
        $this->assertStringContainsString('PORT_CLOSURE', $twinCsv->streamedContent());

        $kpiCsv = $this->actingAs($this->auditor)
            ->get(route('control_tower.kpi.export'))
            ->assertOk();
        $this->assertStringContainsString('text/csv', (string) $kpiCsv->headers->get('content-type'));
        $this->assertStringContainsString('order_ref,customer_ref,item_code', $kpiCsv->streamedContent());
    }
}
