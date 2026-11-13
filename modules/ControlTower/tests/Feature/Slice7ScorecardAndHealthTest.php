<?php

declare(strict_types=1);

namespace Modules\ControlTower\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ControlTower\Application\Services\InventoryHealthOrchestratorService;
use Modules\ControlTower\Application\Services\SupplierScorecardOrchestratorService;
use Modules\ControlTower\Contracts\ItemAvailabilityReader;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\InventoryHealthSnapshot;
use Modules\ControlTower\Domain\Models\SupplierScorecardSnapshot;
use Modules\ControlTower\Domain\Services\InventoryHealthAnalyzer;
use Modules\ControlTower\Domain\Services\SupplierScorecardService;
use Modules\Procurement\Contracts\SupplierLeadTimeStatistics;
use Tests\TestCase;

class Slice7ScorecardAndHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_scorecard_service_classifies_all_tiers_zero_samples_and_monthly_trend(): void
    {
        $fakeStats = new class implements SupplierLeadTimeStatistics
        {
            public function forSupplierAndItem(string $supplierRef, string $itemCode): array
            {
                return match ($supplierRef) {
                    'SUP-STRATEGIC' => ['mean_days' => 7, 'sigma_days' => 0, 'samples' => 15, 'on_time_permil' => 960],
                    'SUP-PREFERRED' => ['mean_days' => 10, 'sigma_days' => 2, 'samples' => 8, 'on_time_permil' => 800],
                    'SUP-WATCHLIST' => ['mean_days' => 10, 'sigma_days' => 4, 'samples' => 5, 'on_time_permil' => 600],
                    'SUP-ATRISK' => ['mean_days' => 10, 'sigma_days' => 7, 'samples' => 2, 'on_time_permil' => 300],
                    default => ['mean_days' => 0, 'sigma_days' => 0, 'samples' => 0, 'on_time_permil' => 0],
                };
            }

            public function primarySupplierForItem(string $itemCode): ?string
            {
                return 'SUP-STRATEGIC';
            }

            public function allStatistics(): array
            {
                return [
                    [
                        'supplier_ref' => 'SUP-STRATEGIC',
                        'supplier_name' => 'PT Baja Presisi Utama',
                        'item_code' => 'PART-A',
                        'mean_days' => 7,
                        'sigma_days' => 0,
                        'samples' => 15,
                        'on_time_permil' => 960,
                    ],
                    [
                        'supplier_ref' => 'SUP-PREFERRED',
                        'supplier_name' => 'PT Logam Nusantara',
                        'item_code' => 'PART-B',
                        'mean_days' => 10,
                        'sigma_days' => 2,
                        'samples' => 8,
                        'on_time_permil' => 800,
                    ],
                    [
                        'supplier_ref' => 'SUP-WATCHLIST',
                        'supplier_name' => 'CV Karya Sentosa',
                        'item_code' => 'PART-C',
                        'mean_days' => 10,
                        'sigma_days' => 4,
                        'samples' => 5,
                        'on_time_permil' => 600,
                    ],
                    [
                        'supplier_ref' => 'SUP-ATRISK',
                        'supplier_name' => 'UD Telat Terus',
                        'item_code' => 'PART-D',
                        'mean_days' => 10,
                        'sigma_days' => 7,
                        'samples' => 2,
                        'on_time_permil' => 300,
                    ],
                    [
                        'supplier_ref' => 'SUP-NOSAMPLE',
                        'supplier_name' => 'PT Pemasok Baru',
                        'item_code' => 'PART-E',
                        'mean_days' => 0,
                        'sigma_days' => 0,
                        'samples' => 0,
                        'on_time_permil' => 0,
                    ],
                ];
            }

            public function monthlyHistory(string $supplierRef, int $months, string $asOfDate): array
            {
                return match ($supplierRef) {
                    'SUP-STRATEGIC' => [
                        ['period' => '2026-09', 'mean_days' => 8, 'on_time_permil' => 880, 'samples' => 4],
                        ['period' => '2026-10', 'mean_days' => 7, 'on_time_permil' => 940, 'samples' => 5],
                        ['period' => '2026-11', 'mean_days' => 7, 'on_time_permil' => 980, 'samples' => 6],
                    ],
                    'SUP-ATRISK' => [
                        ['period' => '2026-09', 'mean_days' => 8, 'on_time_permil' => 700, 'samples' => 1],
                        ['period' => '2026-11', 'mean_days' => 12, 'on_time_permil' => 300, 'samples' => 1],
                    ],
                    default => [],
                };
            }
        };

        $service = new SupplierScorecardService($fakeStats);
        $cards = collect($service->evaluateAll('2026-11-14', 6))->keyBy('supplier_ref');

        $this->assertCount(5, $cards);

        // STRATEGIC (>= 850): 960*0.45 + 1000*0.30 + 1000*0.25 = 432 + 300 + 250 = 982
        $strategic = $cards->get('SUP-STRATEGIC');
        $this->assertSame(982, $strategic['composite_score']);
        $this->assertSame('STRATEGIC', $strategic['tier']);
        $this->assertSame('IMPROVING', $strategic['trend_direction']);
        $this->assertCount(3, $strategic['monthly_history']);
        $this->assertNotEmpty($strategic['action_recommendation']);

        // PREFERRED (700..849): 800*0.45 + 800*0.30 + 800*0.25 = 800
        $preferred = $cards->get('SUP-PREFERRED');
        $this->assertSame(800, $preferred['composite_score']);
        $this->assertSame('PREFERRED', $preferred['tier']);

        // WATCHLIST (500..699): 600*0.45 + 600*0.30 + 500*0.25 = 270 + 180 + 125 = 575
        $watchlist = $cards->get('SUP-WATCHLIST');
        $this->assertSame(575, $watchlist['composite_score']);
        $this->assertSame('WATCHLIST', $watchlist['tier']);

        // AT_RISK (< 500): 300*0.45 + 300*0.30 + 200*0.25 = 135 + 90 + 50 = 275
        $atRisk = $cards->get('SUP-ATRISK');
        $this->assertSame(275, $atRisk['composite_score']);
        $this->assertSame('AT_RISK', $atRisk['tier']);
        $this->assertSame('DECLINING', $atRisk['trend_direction']);

        // Zero samples
        $noSample = $cards->get('SUP-NOSAMPLE');
        $this->assertSame(0, $noSample['composite_score']);
        $this->assertSame(0, $noSample['otd_score']);
        $this->assertSame(0, $noSample['reliability_score']);
        $this->assertSame(0, $noSample['volume_score']);
        $this->assertSame('AT_RISK', $noSample['tier']);
        $this->assertSame('NO_DATA', $noSample['trend_direction']);
        $this->assertStringContainsString('sampel', strtolower($noSample['action_recommendation']));
    }

    public function test_inventory_health_analyzer_covers_all_statuses_aging_buckets_and_bigint_idr_exposure(): void
    {
        $fakeReader = new class implements ItemAvailabilityReader
        {
            public function positionFor(string $itemCode, string $asOfDate): array
            {
                return match ($itemCode) {
                    'SKU-DEAD-AGE' => ['on_hand' => 100, 'available' => 100, 'quarantine' => 0, 'hold' => 0, 'oldest_lot_age_days' => 210],
                    'SKU-DEAD-ZERO' => ['on_hand' => 50, 'available' => 50, 'quarantine' => 0, 'hold' => 0, 'oldest_lot_age_days' => 20],
                    'SKU-SLOW-DOS' => ['on_hand' => 1000, 'available' => 1000, 'quarantine' => 0, 'hold' => 0, 'oldest_lot_age_days' => 45],
                    'SKU-SLOW-AGE' => ['on_hand' => 120, 'available' => 120, 'quarantine' => 0, 'hold' => 0, 'oldest_lot_age_days' => 120],
                    'SKU-EXCESS' => ['on_hand' => 400, 'available' => 400, 'quarantine' => 0, 'hold' => 0, 'oldest_lot_age_days' => 25],
                    'SKU-SHORTAGE' => ['on_hand' => 100, 'available' => 30, 'quarantine' => 70, 'hold' => 0, 'oldest_lot_age_days' => 10],
                    default => ['on_hand' => 200, 'available' => 200, 'quarantine' => 0, 'hold' => 0, 'oldest_lot_age_days' => 15],
                };
            }
        };

        $analyzer = new InventoryHealthAnalyzer($fakeReader);

        // 1. DEAD_STOCK by lot age > 180 + BigInt IDR test (> 32-bit int)
        $deadAge = $analyzer->analyze(
            new EchelonStock(['item_code' => 'SKU-DEAD-AGE', 'echelon_node' => 'DC', 'on_hand_qty' => 100, 'reserved_qty' => 0, 'safety_stock_qty' => 20]),
            dailyDemandQty: 5,
            unitCostIdr: 50_000_000, // 100 * 50,000,000 = 5,000,000,000 (> PHP 32-bit max)
            asOfDate: '2026-11-14',
        );
        $this->assertSame('DEAD_STOCK', $deadAge['health_status']);
        $this->assertSame('>180', $deadAge['aging_bucket']);
        $this->assertIsInt($deadAge['tied_capital_idr']);
        $this->assertIsInt($deadAge['obsolescence_risk_idr']);
        $this->assertSame(5_000_000_000, $deadAge['tied_capital_idr']);
        $this->assertSame(5_000_000_000, $deadAge['obsolescence_risk_idr']);

        // 2. DEAD_STOCK by zero daily demand with stock > 0
        $deadZero = $analyzer->analyze(
            new EchelonStock(['item_code' => 'SKU-DEAD-ZERO', 'echelon_node' => 'DC', 'on_hand_qty' => 50, 'reserved_qty' => 0, 'safety_stock_qty' => 10]),
            dailyDemandQty: 0,
            unitCostIdr: 100_000,
            asOfDate: '2026-11-14',
        );
        $this->assertSame('DEAD_STOCK', $deadZero['health_status']);
        $this->assertSame('0-30', $deadZero['aging_bucket']);
        $this->assertSame(9999, $deadZero['dos_days']);
        $this->assertSame(5_000_000, $deadZero['obsolescence_risk_idr']);

        // 3. SLOW_MOVING by DOS > 90 + bucket 31-90
        $slowDos = $analyzer->analyze(
            new EchelonStock(['item_code' => 'SKU-SLOW-DOS', 'echelon_node' => 'DC', 'on_hand_qty' => 1000, 'reserved_qty' => 0, 'safety_stock_qty' => 50]),
            dailyDemandQty: 10, // DOS = 100 > 90
            unitCostIdr: 10_000,
            asOfDate: '2026-11-14',
        );
        $this->assertSame('SLOW_MOVING', $slowDos['health_status']);
        $this->assertSame('31-90', $slowDos['aging_bucket']);
        $this->assertSame(100, $slowDos['dos_days']);
        $this->assertSame(10_000_000, $slowDos['tied_capital_idr']);
        $this->assertSame(5_000_000, $slowDos['obsolescence_risk_idr']);

        // 4. SLOW_MOVING by lot age 91-180
        $slowAge = $analyzer->analyze(
            new EchelonStock(['item_code' => 'SKU-SLOW-AGE', 'echelon_node' => 'PLANT', 'on_hand_qty' => 120, 'reserved_qty' => 0, 'safety_stock_qty' => 20]),
            dailyDemandQty: 10, // DOS = 12, but age = 120
            unitCostIdr: 20_000,
            asOfDate: '2026-11-14',
        );
        $this->assertSame('SLOW_MOVING', $slowAge['health_status']);
        $this->assertSame('91-180', $slowAge['aging_bucket']);

        // 5. EXCESS (on_hand > safety_stock * 3)
        $excess = $analyzer->analyze(
            new EchelonStock(['item_code' => 'SKU-EXCESS', 'echelon_node' => 'DC', 'on_hand_qty' => 400, 'reserved_qty' => 0, 'safety_stock_qty' => 100]),
            dailyDemandQty: 10, // DOS = 40 <= 90, age = 25
            unitCostIdr: 10_000,
            asOfDate: '2026-11-14',
        );
        $this->assertSame('EXCESS', $excess['health_status']);
        $this->assertSame('0-30', $excess['aging_bucket']);
        // Excess qty = 400 - 300 = 100 -> 1,000,000
        $this->assertSame(1_000_000, $excess['obsolescence_risk_idr']);

        // 6. SHORTAGE_RISK (available < safety_stock due to quarantine)
        $shortage = $analyzer->analyze(
            new EchelonStock(['item_code' => 'SKU-SHORTAGE', 'echelon_node' => 'PLANT', 'on_hand_qty' => 100, 'reserved_qty' => 0, 'safety_stock_qty' => 50]),
            dailyDemandQty: 10,
            unitCostIdr: 10_000,
            asOfDate: '2026-11-14',
        );
        $this->assertSame('SHORTAGE_RISK', $shortage['health_status']);
        $this->assertSame(30, $shortage['available_qty']);
        $this->assertSame(70, $shortage['quarantine_qty']);
        $this->assertSame(700_000, $shortage['obsolescence_risk_idr']);

        // 7. HEALTHY
        $healthy = $analyzer->analyze(
            new EchelonStock(['item_code' => 'SKU-HEALTHY', 'echelon_node' => 'DC', 'on_hand_qty' => 200, 'reserved_qty' => 0, 'safety_stock_qty' => 100]),
            dailyDemandQty: 10,
            unitCostIdr: 10_000,
            asOfDate: '2026-11-14',
        );
        $this->assertSame('HEALTHY', $healthy['health_status']);
        $this->assertSame('0-30', $healthy['aging_bucket']);
        $this->assertSame(2_000_000, $healthy['tied_capital_idr']);
        $this->assertSame(0, $healthy['obsolescence_risk_idr']);
    }

    public function test_supplier_scorecard_command_and_orchestrator_persist_idempotently(): void
    {
        $this->app->instance(SupplierLeadTimeStatistics::class, new class implements SupplierLeadTimeStatistics
        {
            public function forSupplierAndItem(string $supplierRef, string $itemCode): array
            {
                return ['mean_days' => 5, 'sigma_days' => 1, 'samples' => 12, 'on_time_permil' => 920];
            }

            public function primarySupplierForItem(string $itemCode): ?string
            {
                return 'SUP-CMD-01';
            }

            public function allStatistics(): array
            {
                return [
                    [
                        'supplier_ref' => 'SUP-CMD-01',
                        'supplier_name' => 'PT Prima Komponen',
                        'item_code' => 'CMP-01',
                        'mean_days' => 5,
                        'sigma_days' => 1,
                        'samples' => 12,
                        'on_time_permil' => 920,
                    ],
                ];
            }

            public function monthlyHistory(string $supplierRef, int $months, string $asOfDate): array
            {
                return [
                    ['period' => '2026-10', 'mean_days' => 6, 'on_time_permil' => 860, 'samples' => 5],
                    ['period' => '2026-11', 'mean_days' => 5, 'on_time_permil' => 920, 'samples' => 7],
                ];
            }
        });

        $this->artisan('tower:supplier-scorecard', [
            '--supplier' => 'SUP-CMD-01',
            '--period' => '2026-11',
        ])
            ->expectsOutputToContain('Generated 1 supplier scorecard snapshot(s).')
            ->assertExitCode(0);

        // Run a second time for same supplier + period -> idempotent upsert (still 1 row)
        app(SupplierScorecardOrchestratorService::class)->generateForPeriod('2026-11', 'SUP-CMD-01');

        $this->assertSame(1, SupplierScorecardSnapshot::query()->where('supplier_ref', 'SUP-CMD-01')->where('period', '2026-11')->count());
        $snapshot = SupplierScorecardSnapshot::query()->where('supplier_ref', 'SUP-CMD-01')->firstOrFail();
        $this->assertSame('STRATEGIC', $snapshot->tier);
        $this->assertIsArray($snapshot->trend_json);
    }

    public function test_http_endpoints_csv_export_and_rbac_for_suppliers_and_inventory_health(): void
    {
        $this->app->instance(SupplierLeadTimeStatistics::class, new class implements SupplierLeadTimeStatistics
        {
            public function forSupplierAndItem(string $supplierRef, string $itemCode): array
            {
                return ['mean_days' => 8, 'sigma_days' => 1, 'samples' => 10, 'on_time_permil' => 900];
            }

            public function primarySupplierForItem(string $itemCode): ?string
            {
                return 'SUP-HTTP-01';
            }

            public function allStatistics(): array
            {
                return [
                    [
                        'supplier_ref' => 'SUP-HTTP-01',
                        'supplier_name' => 'PT Mitra Logistik',
                        'item_code' => 'ITEM-01',
                        'mean_days' => 8,
                        'sigma_days' => 1,
                        'samples' => 10,
                        'on_time_permil' => 900,
                    ],
                ];
            }

            public function monthlyHistory(string $supplierRef, int $months, string $asOfDate): array
            {
                return [['period' => '2026-11', 'mean_days' => 8, 'on_time_permil' => 900, 'samples' => 10]];
            }
        });

        EchelonStock::query()->create([
            'item_code' => 'BRG-HEALTH-01',
            'item_name' => 'Bearing Presisi',
            'echelon_node' => 'DC',
            'on_hand_qty' => 250,
            'in_transit_qty' => 0,
            'reserved_qty' => 10,
            'safety_stock_qty' => 100,
            'abc_class' => 'A',
            'xyz_class' => 'X',
        ]);

        $planner = User::factory()->create(['role' => 'planner']);
        $auditor = User::factory()->create(['role' => 'auditor']);
        $unauthorized = User::factory()->create(['role' => 'operator']);

        // Unauthorized role gets 403 everywhere
        $this->actingAs($unauthorized)->get(route('control_tower.suppliers.index'))->assertForbidden();
        $this->actingAs($unauthorized)->post(route('control_tower.suppliers.refresh'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('control_tower.suppliers.export'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('control_tower.inventory_health.index'))->assertForbidden();
        $this->actingAs($unauthorized)->post(route('control_tower.inventory_health.refresh'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('control_tower.inventory_health.export'))->assertForbidden();

        // Auditor can read and export, but gets 403 on POST refresh
        $this->actingAs($auditor)->get(route('control_tower.suppliers.index'))->assertOk();
        $this->actingAs($auditor)->get(route('control_tower.suppliers.export'))->assertOk();
        $this->actingAs($auditor)->post(route('control_tower.suppliers.refresh'))->assertForbidden();
        $this->actingAs($auditor)->get(route('control_tower.inventory_health.index'))->assertOk();
        $this->actingAs($auditor)->get(route('control_tower.inventory_health.export'))->assertOk();
        $this->actingAs($auditor)->post(route('control_tower.inventory_health.refresh'))->assertForbidden();

        // Planner can POST refresh & view populated pages + CSV exports
        $this->actingAs($planner)
            ->post(route('control_tower.suppliers.refresh'), ['period' => '2026-11'])
            ->assertRedirect(route('control_tower.suppliers.index'));

        $this->actingAs($planner)
            ->get(route('control_tower.suppliers.index'))
            ->assertOk()
            ->assertSee('PT Mitra Logistik')
            ->assertSee('STRATEGIC');

        $csvSup = $this->actingAs($planner)->get(route('control_tower.suppliers.export'));
        $csvSup->assertOk();
        $this->assertStringContainsString('supplier_ref,supplier_name,period', $csvSup->streamedContent());
        $this->assertStringContainsString('SUP-HTTP-01', $csvSup->streamedContent());

        $this->actingAs($planner)
            ->post(route('control_tower.inventory_health.refresh'), ['snapshot_date' => '2026-11-14'])
            ->assertRedirect(route('control_tower.inventory_health.index'));

        // Idempotency check on inventory health orchestrator
        app(InventoryHealthOrchestratorService::class)->analyzeAll('2026-11-14');
        $this->assertSame(1, InventoryHealthSnapshot::query()->where('item_code', 'BRG-HEALTH-01')->whereDate('snapshot_date', '2026-11-14')->count());

        $this->actingAs($planner)
            ->get(route('control_tower.inventory_health.index'))
            ->assertOk()
            ->assertSee('BRG-HEALTH-01');

        $csvHealth = $this->actingAs($planner)->get(route('control_tower.inventory_health.export'));
        $csvHealth->assertOk();
        $this->assertStringContainsString('item_code,echelon_node,health_status,aging_bucket', $csvHealth->streamedContent());
        $this->assertStringContainsString('BRG-HEALTH-01', $csvHealth->streamedContent());
    }
}
