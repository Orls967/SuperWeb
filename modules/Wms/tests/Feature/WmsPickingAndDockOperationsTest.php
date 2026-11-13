<?php

declare(strict_types=1);

namespace Modules\Wms\tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Store\Domain\Models\Category;
use Modules\Store\Domain\Models\Product;
use Modules\Wms\Application\Services\WmsOperationalAnalyticsService;
use Modules\Wms\Domain\Models\Bin;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\DockAppointment;
use Modules\Wms\Domain\Models\Rack;
use Modules\Wms\Domain\Models\Slotting;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Models\Wave;
use Modules\Wms\Domain\Models\Zone;
use Modules\Wms\Domain\Services\DockSchedulingOptimizationService;
use Modules\Wms\Domain\Services\WavePickPathOptimizationService;
use Tests\TestCase;

class WmsPickingAndDockOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    private function createProduct(): int
    {
        $category = Category::create([
            'name' => 'Kat Uji '.uniqid(),
            'slug' => 'kat-uji-'.uniqid(),
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'SKU Uji '.uniqid(),
            'slug' => 'sku-uji-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'price' => 25_000,
            'cached_stock' => 200,
            'is_car' => false,
        ]);

        return (int) $product->id;
    }

    /**
     * @return array{Warehouse, Zone, Bin, Bin}
     */
    private function createWarehouseWithBins(): array
    {
        $wh = Warehouse::create([
            'code' => 'WH-'.substr(md5((string) mt_rand()), 0, 6),
            'name' => 'Gudang Logistik',
            'kind' => 'dc',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);
        $zone = Zone::create([
            'warehouse_id' => $wh->id,
            'code' => 'PICK-A',
            'name' => 'Zona Pick A',
            'kind' => 'pick',
            'is_active' => true,
        ]);
        $rack = Rack::create([
            'zone_id' => $zone->id,
            'code' => 'R1',
        ]);
        $bin1 = Bin::create([
            'rack_id' => $rack->id,
            'code' => 'B-01',
            'capacity_units' => 500,
            'is_pick_face' => true,
            'is_active' => true,
        ]);
        $bin2 = Bin::create([
            'rack_id' => $rack->id,
            'code' => 'B-04',
            'capacity_units' => 500,
            'is_pick_face' => false,
            'is_active' => true,
        ]);

        return [$wh, $zone, $bin1, $bin2];
    }

    public function test_picking_and_docks_routes_enforce_rbac_and_render_design_system_views(): void
    {
        $this->get(route('wms.picking'))->assertRedirect();
        $this->get(route('wms.docks'))->assertRedirect();

        foreach (['admin', 'hub_operator', 'procurement', 'auditor'] as $allowedRole) {
            $user = User::factory()->create(['role' => $allowedRole]);

            $this->actingAs($user)
                ->get(route('wms.picking'))
                ->assertOk()
                ->assertSee('WMS Wave Picking')
                ->assertSee('Rencana Lintasan Pick per Zona');

            $this->actingAs($user)
                ->get(route('wms.docks'))
                ->assertOk()
                ->assertSee('WMS Cross-Docking')
                ->assertSee('Jadwal Dermaga');
        }

        $planner = User::factory()->create(['role' => 'planner']);
        $this->actingAs($planner)->get(route('wms.picking'))->assertForbidden();
        $this->actingAs($planner)->get(route('wms.docks'))->assertForbidden();
    }

    public function test_allocate_wave_and_cross_dock_routes_respect_operator_rbac_and_export_csv(): void
    {
        [$originWh, , $bin1] = $this->createWarehouseWithBins();
        [$destWh] = $this->createWarehouseWithBins();
        $productId = $this->createProduct();

        BinStock::create([
            'bin_id' => $bin1->id,
            'product_id' => $productId,
            'qty' => 80,
            'status' => 'available',
        ]);

        $dock = DockAppointment::create([
            'warehouse_id' => $originWh->id,
            'direction' => 'in',
            'reference' => 'INB-ROUTE-001',
            'window_start' => CarbonImmutable::parse('2026-11-14 08:00:00'),
            'window_end' => CarbonImmutable::parse('2026-11-14 10:00:00'),
            'carrier' => 'JNE-CARGO',
            'status' => 'scheduled',
        ]);

        $auditor = User::factory()->create(['role' => 'auditor']);
        $this->actingAs($auditor)
            ->postJson(route('wms.picking.allocate'), [
                'strategy' => 'fifo',
                'requirements' => [['product_id' => $productId, 'qty' => 10]],
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->postJson(route('wms.docks.cross_dock'), [
                'dock_appointment_id' => $dock->id,
                'origin_warehouse_id' => $originWh->id,
                'destination_warehouse_id' => $destWh->id,
                'items' => [['product_id' => $productId, 'qty' => 5]],
            ])
            ->assertForbidden();

        $hubOperator = User::factory()->create(['role' => 'hub_operator']);
        $this->actingAs($hubOperator)
            ->postJson(route('wms.picking.allocate'), [
                'strategy' => 'fifo',
                'requirements' => [['product_id' => $productId, 'qty' => 10]],
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'task_count' => 1]);

        $this->actingAs($hubOperator)
            ->postJson(route('wms.docks.cross_dock'), [
                'dock_appointment_id' => $dock->id,
                'origin_warehouse_id' => $originWh->id,
                'destination_warehouse_id' => $destWh->id,
                'items' => [['product_id' => $productId, 'qty' => 5]],
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'in_transit']);

        $pickingCsvResponse = $this->actingAs($auditor)->get(route('wms.picking.export'));
        $pickingCsvResponse->assertOk();
        $pickingCsvResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('wave_id,wave_code,strategy,wave_status', (string) $pickingCsvResponse->getContent());
        $this->assertStringContainsString((string) $productId, (string) $pickingCsvResponse->getContent());

        $docksCsvResponse = $this->actingAs($auditor)->get(route('wms.docks.export'));
        $docksCsvResponse->assertOk();
        $docksCsvResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('appointment_id,warehouse_code,dock_door,direction', (string) $docksCsvResponse->getContent());
        $this->assertStringContainsString('INB-ROUTE-001', (string) $docksCsvResponse->getContent());
    }

    public function test_wave_pick_path_optimization_service_groups_zones_plans_manhattan_path_and_detects_congestion(): void
    {
        $service = app(WavePickPathOptimizationService::class);

        $emptyResult = $service->optimize([]);
        $this->assertSame(0, $emptyResult['total_zones']);
        $this->assertSame(0, $emptyResult['estimated_total_seconds']);

        $result = $service->optimize([
            ['zone_code' => 'Z-B', 'aisle' => 2, 'pos' => 12, 'bin_id' => 'R2-B12', 'qty' => 4, 'is_pick_face' => true],
            ['zone_code' => 'Z-A', 'aisle' => 1, 'pos' => 2, 'bin_id' => 'R1-B02', 'qty' => 5, 'is_pick_face' => true],
            ['zone_code' => 'Z-A', 'aisle' => 1, 'pos' => 2, 'bin_id' => 'R1-B02', 'qty' => 3, 'is_pick_face' => true],
            ['zone_code' => 'Z-A', 'aisle' => 1, 'pos' => 2, 'bin_id' => 'R1-B02', 'qty' => 2, 'is_pick_face' => true],
            ['zone_code' => 'Z-A', 'aisle' => 2, 'pos' => 6, 'bin_id' => 'R2-B06', 'qty' => 6, 'is_pick_face' => true],
        ], aisleWidth: 4, aisleLength: 20, congestionThreshold: 3);

        $this->assertSame(2, $result['total_zones']);
        $this->assertSame(5, $result['total_picks']);
        $this->assertSame(20, $result['total_units']);
        $this->assertSame(1, $result['congestion_count']);
        $this->assertSame('Z-A', $result['congested_pick_faces'][0]['zone_code']);
        $this->assertSame('R1-B02', $result['congested_pick_faces'][0]['bin_id']);
        $this->assertSame('MODERATE', $result['congested_pick_faces'][0]['severity']);
        $this->assertGreaterThan(0, $result['total_manhattan_distance']);
        $this->assertGreaterThan($result['total_manhattan_distance'], $result['estimated_total_seconds']);
        $this->assertSame('Z-A', $result['zone_plans'][0]['zone_code']);
        $this->assertNotEmpty($result['zone_plans'][0]['certificate_hash']);
    }

    public function test_dock_scheduling_optimization_service_detects_conflicts_scores_sla_and_recommends_reserve_door(): void
    {
        $service = app(DockSchedulingOptimizationService::class);

        $evaluation = $service->evaluateSchedule([
            [
                'appointment_id' => 101,
                'dock_door' => 'D-01',
                'direction' => 'in',
                'window_start_min' => 480,
                'window_end_min' => 570,
                'pallet_count' => 12,
                'status' => 'scheduled',
            ],
            [
                'appointment_id' => 102,
                'dock_door' => 'D-01',
                'direction' => 'outbound',
                'window_start_min' => 540,
                'window_end_min' => 600,
                'cutoff_min' => 610,
                'pallet_count' => 15,
                'status' => 'scheduled',
            ],
            [
                'appointment_id' => 103,
                'dock_door' => 'D-03',
                'direction' => 'cross_dock',
                'window_start_min' => 600,
                'window_end_min' => 690,
                'pallet_count' => 8,
                'status' => 'scheduled',
            ],
        ], ['D-01', 'D-02', 'D-03', 'D-04']);

        $this->assertSame(3, $evaluation['active_appointments']);
        $this->assertSame(1, $evaluation['conflict_count']);
        $this->assertSame(30, $evaluation['conflicts'][0]['overlap_minutes']);
        $this->assertSame('D-02', $evaluation['conflicts'][0]['recommended_door_for_b']);
        $this->assertLessThan(10000, $evaluation['average_sla_score_bps']);
        $this->assertGreaterThanOrEqual(1, $evaluation['high_risk_count']);

        $candidateCheck = $service->scheduleWindow([
            'dock_door' => 'D-01',
            'direction' => 'cross_dock',
            'window_start_min' => 500,
            'window_end_min' => 560,
            'pallet_count' => 10,
        ], [
            [
                'appointment_id' => 101,
                'dock_door' => 'D-01',
                'direction' => 'inbound',
                'window_start_min' => 480,
                'window_end_min' => 570,
            ],
        ], ['D-01', 'D-02', 'D-03']);

        $this->assertFalse($candidateCheck['scheduled']);
        $this->assertSame('D-02', $candidateCheck['alternative_door']);
        $this->assertSame('D-02', $candidateCheck['assigned_door']);
    }

    public function test_wms_operational_analytics_service_computes_permil_metrics_and_csv_exports(): void
    {
        [$wh, $zone, $binPick, $binStorage] = $this->createWarehouseWithBins();
        $productA = $this->createProduct();
        $productC = $this->createProduct();

        BinStock::create([
            'bin_id' => $binPick->id,
            'product_id' => $productA,
            'qty' => 30,
            'status' => 'available',
            'stock_status' => 'available',
        ]);
        $releasedStock = BinStock::create([
            'bin_id' => $binStorage->id,
            'product_id' => $productC,
            'qty' => 20,
            'status' => 'available',
        ]);
        $releasedStock->stock_status = 'available';
        $releasedStock->quarantined_at = CarbonImmutable::parse('2026-11-10 09:00:00');
        $releasedStock->released_at = CarbonImmutable::parse('2026-11-11 10:00:00');
        $releasedStock->save();

        $activeQuarantine = BinStock::create([
            'bin_id' => $binStorage->id,
            'product_id' => $productA,
            'qty' => 5,
            'status' => 'quarantine',
        ]);
        $activeQuarantine->stock_status = 'quarantine';
        $activeQuarantine->quarantined_at = CarbonImmutable::parse('2026-11-12 09:00:00');
        $activeQuarantine->save();

        Slotting::create([
            'product_id' => $productA,
            'abc_class' => 'A',
            'annual_value_idr' => 100_000_000,
            'suggested_zone_id' => $zone->id,
        ]);
        Slotting::create([
            'product_id' => $productC,
            'abc_class' => 'C',
            'annual_value_idr' => 5_000_000,
            'suggested_zone_id' => null,
        ]);

        $wave = Wave::create([
            'code' => 'WAVE-TEST-001',
            'status' => 'released',
            'strategy' => 'fifo',
            'released_at' => CarbonImmutable::parse('2026-11-14 07:30:00'),
        ]);

        Task::create([
            'kind' => 'pick',
            'status' => 'done',
            'product_id' => $productA,
            'qty' => 10,
            'bin_id' => $binPick->id,
            'wave_id' => $wave->id,
        ]);
        Task::create([
            'kind' => 'pick',
            'status' => 'open',
            'product_id' => $productA,
            'qty' => 5,
            'bin_id' => $binPick->id,
            'wave_id' => $wave->id,
        ]);

        DockAppointment::create([
            'warehouse_id' => $wh->id,
            'direction' => 'out',
            'reference' => 'OUT-TEST-999',
            'window_start' => CarbonImmutable::parse('2026-11-14 08:00:00'),
            'window_end' => CarbonImmutable::parse('2026-11-14 10:00:00'),
            'carrier' => 'SICEPAT',
            'status' => 'scheduled',
        ]);

        /** @var WmsOperationalAnalyticsService $analytics */
        $analytics = app(WmsOperationalAnalyticsService::class);
        $summary = $analytics->summarize();

        $this->assertSame(500, $summary['picking_wave_completion_permil']);
        $this->assertGreaterThan(0, $summary['dock_utilization_permil']);
        $this->assertSame(1000, $summary['slotting_abc_accuracy_permil']);
        $this->assertSame(500, $summary['quarantine_release_rate_permil']);
        $this->assertSame(2, $summary['total_pick_tasks']);
        $this->assertSame(1, $summary['completed_pick_tasks']);
        $this->assertSame(1, $summary['open_pick_tasks']);

        $pickingCsv = $analytics->buildPickingCsv();
        $this->assertStringContainsString('WAVE-TEST-001', $pickingCsv);

        $docksCsv = $analytics->buildDocksCsv();
        $this->assertStringContainsString('OUT-TEST-999', $docksCsv);
    }
}
