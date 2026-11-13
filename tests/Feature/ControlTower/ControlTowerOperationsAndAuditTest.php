<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ControlTower\Application\Services\ControlTowerKpiAggregatorService;
use Modules\ControlTower\Application\Services\ControlTowerWhatIfTwinService;
use Modules\ControlTower\Application\Services\DemandConsensusForecastService;
use Modules\ControlTower\Application\Services\DisruptionResponsePlaybookService;
use Modules\ControlTower\Application\Services\MultiEchelonInventoryService;
use Modules\ControlTower\Application\Services\OrderPromiseOrchestratorService;
use Modules\ControlTower\Application\Services\SopWorkflowBoardService;
use Modules\ControlTower\Domain\Models\EchelonStock;

uses(RefreshDatabase::class);

it('runs full sop lifecycle, forecast override, twin simulation, and http endpoints', function (): void {
    $this->seed(DatabaseSeeder::class);
    $admin = User::query()->where('role', 'admin')->firstOrFail();

    $sopService = app(SopWorkflowBoardService::class);
    $cycle = $sopService->initiateWithFinancials('2026-11', 'moderate', 1000, 1050, 50_000, 30_000, 1_000, 8500, $admin->id);
    $cycle = $sopService->advance($cycle, 'demand_review', $admin->id, 'Demand reviewed');
    $cycle = $sopService->advance($cycle, 'supply_review', $admin->id, 'Supply reviewed');
    $cycle = $sopService->advance($cycle, 'pre_sop_balancing', $admin->id, 'Balanced');
    $cycle = $sopService->advance($cycle, 'executive_signoff', $admin->id, 'Signed off');
    expect($cycle->status)->toBe('executive_signoff');

    $fcService = app(DemandConsensusForecastService::class);
    $fc = $fcService->generateBestConsensusForecast('SKU-CT-10', '2026-11', [100, 110, 120, 130]);
    $override = $fcService->applySalesOverride($fc, 155, 'Promo akhir tahun', $admin->id);
    expect($override->new_qty)->toBe(155);

    EchelonStock::query()->create([
        'item_code' => 'SKU-CT-10',
        'item_name' => 'Produk Control Tower',
        'echelon_node' => 'DC',
        'on_hand_qty' => 80,
        'in_transit_qty' => 20,
        'reserved_qty' => 10,
        'safety_stock_qty' => 15,
        'abc_class' => 'A',
        'xyz_class' => 'X',
    ]);

    $network = app(MultiEchelonInventoryService::class)->inspectItemNetwork('SKU-CT-10');
    expect($network['summary']['net_available'])->toBe(90);

    $promise = app(OrderPromiseOrchestratorService::class)->promiseOrder('SO-CT-100', 'SKU-CT-10', 60);
    expect($promise->atp_confirmed_qty)->toBe(60);

    $blast = app(DisruptionResponsePlaybookService::class)->raiseDisruptionWithBlastRadius('PORT-SBY', 'PORT_CONGESTION', 'Antrean Pelabuhan', [
        ['order_ref' => 'SO-CT-100', 'item_code' => 'SKU-CT-10', 'echelon_path' => ['PORT-SBY', 'DC'], 'value_idr' => 120_000_000],
    ]);
    expect($blast['affected_orders_count'])->toBe(1);

    $twin = app(ControlTowerWhatIfTwinService::class)->simulatePortClosure14Days(100);
    expect($twin->at_risk_orders_count)->toBeGreaterThan(0);

    $snap = app(ControlTowerKpiAggregatorService::class)->recordDailySnapshot();
    expect($snap->otif_bps)->toBeGreaterThan(0);

    $this->actingAs($admin)->get(route('control_tower.sop.index'))->assertOk();
    $this->actingAs($admin)->get(route('control_tower.forecasting.index'))->assertOk();
    $this->actingAs($admin)->get(route('control_tower.twin.index'))->assertOk();
    $this->actingAs($admin)->get(route('control_tower.kpi.index'))->assertOk();
    $this->actingAs($admin)->get(route('control_tower.safety_stock.index'))->assertOk();

    $this->artisan('tower:audit')->assertExitCode(0);
    $this->artisan('tower:safety-stock-recalc')->assertExitCode(0);
    $this->artisan('tower:twin-simulate')->assertExitCode(0);
    $this->artisan('tower:kpi-snapshot')->assertExitCode(0);
});
