<?php

declare(strict_types=1);

namespace Tests\Acceptance\Culinary;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Vending\Application\Services\VendingCatalogService;
use Modules\Vending\Application\Services\VendingDispenseService;
use Modules\Vending\Application\Services\VendingForecastService;
use Modules\Vending\Application\Services\VendingRestockService;
use Modules\Vending\Domain\Models\VendingItem;
use Modules\Vending\Domain\Models\VendingTransaction;
use Modules\Vending\Domain\Models\VendingUnit;
use Tests\TestCase;

/**
 * Acceptance Tests: LINI 5 Kuliner - Smart Vending (§3F Slices 3 & 4).
 *
 * Menguji 35 aturan bisnis Smart Vending:
 * - Vending unit as inventory stock location & geolocation
 * - Item catalog with shelf life & dynamic markdown
 * - Dispense with idempotency, stock decrement, and ledger settlement
 * - Biometric token hash verification
 * - Holt-Winters 7-day demand & waste forecasting (B0′.5)
 * - Auto-replenishment PO with plafon limit
 * - Restock task generation anti-duplicate
 * - Route optimization for restocking (B0′.6) with certificate
 * - Sensor health and maintenance mode
 * - Append-only facts on transactions
 * - HTTP authorization routes (/vending)
 * - Audit two-source vnd:audit & corruption fixture
 */
class VendingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $operator;

    protected User $regularCustomer;

    protected VendingUnit $unit;

    protected VendingItem $item;

    protected SimClockInterface $simClock;

    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->simClock = app(SimClockInterface::class);
        $this->ledgerService = app(LedgerService::class);

        $this->operator = User::factory()->create(['name' => 'Operator Rudi', 'role' => 'vending_operator']);
        $this->regularCustomer = User::factory()->create(['name' => 'Customer Budi', 'role' => 'customer']);

        $this->unit = VendingUnit::create([
            'unit_code' => 'VND-JKT-01',
            'location_name' => 'Stasiun MRT Bundaran HI',
            'latitude' => -6193125, // -6.193125 * 1e6
            'longitude' => 106822984, // 106.822984 * 1e6
            'current_stock' => 50,
            'max_capacity' => 100,
            'critical_threshold' => 15,
            'status' => 'active',
            'total_sales_count' => 0,
            'total_sales_idr' => 0,
        ]);

        $this->item = VendingItem::create([
            'item_code' => 'ITEM-KOPI-SUSU',
            'name' => 'Kopi Susu Gula Aren 250ml',
            'category' => 'beverage',
            'base_price_idr' => 18_000,
            'cost_price_idr' => 8_000,
            'shelf_life_hours' => 72,
        ]);
    }

    public function test_01_vending_unit_registration_as_inventory_stock_location(): void
    {
        $restockService = app(VendingRestockService::class);

        $newUnit = $restockService->registerUnit([
            'unit_code' => 'VND-BDG-02',
            'location_name' => 'Stasiun Bandung Pintu Utara',
            'latitude' => -6917464,
            'longitude' => 107604543,
            'max_capacity' => 80,
            'critical_threshold' => 10,
        ]);

        expect($newUnit->unit_code)->toBe('VND-BDG-02')
            ->and($newUnit->current_stock)->toBe(0)
            ->and($newUnit->status)->toBe('active');
    }

    public function test_02_vending_unit_geolocation_coordinates_recording(): void
    {
        expect($this->unit->latitude)->toBe(-6193125)
            ->and($this->unit->longitude)->toBe(106822984);
    }

    public function test_03_item_catalog_registration_with_base_price_and_shelf_life(): void
    {
        $catalogService = app(VendingCatalogService::class);

        $item = $catalogService->registerItem([
            'item_code' => 'ITEM-SANDWICH-TUNA',
            'name' => 'Artisan Tuna Mayo Sandwich',
            'category' => 'fresh_meal',
            'base_price_idr' => 25_000,
            'cost_price_idr' => 12_000,
            'shelf_life_hours' => 24,
        ]);

        expect($item->item_code)->toBe('ITEM-SANDWICH-TUNA')
            ->and($item->base_price_idr)->toBe(25_000)
            ->and($item->shelf_life_hours)->toBe(24);
    }

    public function test_04_vending_item_dispense_requires_valid_idempotency_key(): void
    {
        $dispenseService = app(VendingDispenseService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency key is required.');

        $dispenseService->dispenseItem(
            unitId: $this->unit->id,
            itemCode: $this->item->item_code,
            qty: 1,
            paymentMethod: 'QRIS',
            idempotencyKey: ''
        );
    }

    public function test_05_vending_item_dispense_decrements_current_stock(): void
    {
        $dispenseService = app(VendingDispenseService::class);

        $tx = $dispenseService->dispenseItem(
            unitId: $this->unit->id,
            itemCode: $this->item->item_code,
            qty: 2,
            paymentMethod: 'QRIS',
            idempotencyKey: 'vnd:dsp:005'
        );

        expect($tx->status)->toBe('COMPLETED')
            ->and($this->unit->fresh()->current_stock)->toBe(48)
            ->and($this->unit->fresh()->total_sales_count)->toBe(2)
            ->and($this->unit->fresh()->total_sales_idr)->toBe(36_000);
    }

    public function test_06_vending_dispense_fails_if_stock_insufficient(): void
    {
        $dispenseService = app(VendingDispenseService::class);

        $this->unit->update(['current_stock' => 1]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient stock in vending unit');

        $dispenseService->dispenseItem(
            unitId: $this->unit->id,
            itemCode: $this->item->item_code,
            qty: 2,
            paymentMethod: 'QRIS',
            idempotencyKey: 'vnd:dsp:006'
        );
    }

    public function test_07_vending_dispense_posts_double_entry_ledger_revenue(): void
    {
        $dispenseService = app(VendingDispenseService::class);

        $dispenseService->dispenseItem(
            unitId: $this->unit->id,
            itemCode: $this->item->item_code,
            qty: 1,
            paymentMethod: 'QRIS',
            idempotencyKey: 'vnd:dsp:007'
        );

        $rev = $this->ledgerService->getCachedBalance('vnd:sales_revenue:IDR');
        expect((int) $rev)->toBe(18_000);
    }

    public function test_08_vending_dispense_posts_cogs_and_inventory_movement(): void
    {
        $dispenseService = app(VendingDispenseService::class);

        $dispenseService->dispenseItem(
            unitId: $this->unit->id,
            itemCode: $this->item->item_code,
            qty: 1,
            paymentMethod: 'QRIS',
            idempotencyKey: 'vnd:dsp:008'
        );

        $cogs = $this->ledgerService->getCachedBalance('expense:resto:cogs:IDR');
        expect((int) $cogs)->toBe(8_000);
    }

    public function test_09_vending_transaction_records_biometric_token_hash(): void
    {
        $dispenseService = app(VendingDispenseService::class);

        $token = 'BIO-FACE-ENCLAVE-998811';
        $tx = $dispenseService->dispenseItem(
            unitId: $this->unit->id,
            itemCode: $this->item->item_code,
            qty: 1,
            paymentMethod: 'BIOMETRIC',
            idempotencyKey: 'vnd:dsp:009',
            biometricToken: $token
        );

        expect($tx->biometric_token_hash)->toBe(hash('sha256', $token));
    }

    public function test_10_dynamic_near_expiry_markdown_flash_clearance_50(): void
    {
        $catalogService = app(VendingCatalogService::class);

        // <= 6 hours remaining: 50% discount
        $pricing = $catalogService->calculateDynamicPrice($this->item->item_code, 4);

        expect($pricing['discount_pct'])->toBe(50)
            ->and($pricing['final_price_idr'])->toBe(9_000)
            ->and($pricing['tier'])->toBe('FLASH_CLEARANCE_50');
    }

    public function test_11_dynamic_near_expiry_markdown_near_expiry_30(): void
    {
        $catalogService = app(VendingCatalogService::class);

        // <= 24 hours remaining: 30% discount
        $pricing = $catalogService->calculateDynamicPrice($this->item->item_code, 18);

        expect($pricing['discount_pct'])->toBe(30)
            ->and($pricing['final_price_idr'])->toBe(12_600)
            ->and($pricing['tier'])->toBe('NEAR_EXPIRY_30');
    }

    public function test_12_dynamic_near_expiry_markdown_early_markdown_15(): void
    {
        $catalogService = app(VendingCatalogService::class);

        // <= 48 hours remaining: 15% discount
        $pricing = $catalogService->calculateDynamicPrice($this->item->item_code, 36);

        expect($pricing['discount_pct'])->toBe(15)
            ->and($pricing['final_price_idr'])->toBe(15_300)
            ->and($pricing['tier'])->toBe('EARLY_MARKDOWN_15');
    }

    public function test_13_dynamic_near_expiry_markdown_standard_price(): void
    {
        $catalogService = app(VendingCatalogService::class);

        // > 48 hours remaining: 0% discount
        $pricing = $catalogService->calculateDynamicPrice($this->item->item_code, 60);

        expect($pricing['discount_pct'])->toBe(0)
            ->and($pricing['final_price_idr'])->toBe(18_000)
            ->and($pricing['tier'])->toBe('STANDARD_PRICE');
    }

    public function test_14_holt_winters_forecast_uses_deterministic_seed(): void
    {
        $forecastService = app(VendingForecastService::class);

        $salesHistory = [40, 42, 45, 48, 50, 52, 55, 41, 43, 47, 49, 51, 53, 56];
        $f1 = $forecastService->generate7DayForecast($this->unit->unit_code, $this->item->item_code, $salesHistory, seed: 42);
        $f2 = $forecastService->generate7DayForecast($this->unit->unit_code, $this->item->item_code, $salesHistory, seed: 42);

        expect($f1->forecast_qty)->toBe($f2->forecast_qty)
            ->and($f1->waste_forecast_qty)->toBe($f2->waste_forecast_qty);
    }

    public function test_15_holt_winters_forecast_generates_positive_integer_projection(): void
    {
        $forecastService = app(VendingForecastService::class);

        $salesHistory = [20, 25, 22, 28, 30, 32, 35];
        $forecast = $forecastService->generate7DayForecast($this->unit->unit_code, $this->item->item_code, $salesHistory, seed: 42);

        expect($forecast->forecast_qty)->toBeGreaterThan(0)
            ->and($forecast->algorithm_model)->toBe('HOLT_WINTERS_7D');
    }

    public function test_16_holt_winters_projected_waste_calculation(): void
    {
        $forecastService = app(VendingForecastService::class);

        $salesHistory = [50, 50, 50, 50, 50, 50, 50];
        $forecast = $forecastService->generate7DayForecast($this->unit->unit_code, $this->item->item_code, $salesHistory, seed: 42);

        // Waste projected at 8% of forecast
        expect($forecast->waste_forecast_qty)->toBe((int) round($forecast->forecast_qty * 0.08));
    }

    public function test_17_holt_winters_actual_recording_computes_mape_bps(): void
    {
        $forecastService = app(VendingForecastService::class);

        $salesHistory = [50, 50, 50, 50, 50, 50, 50];
        $forecast = $forecastService->generate7DayForecast($this->unit->unit_code, $this->item->item_code, $salesHistory, seed: 42);

        $updated = $forecastService->recordActualDemandAndWaste($forecast->id, actualDemand: 52, actualWaste: 4);

        expect($updated->mape_percent)->toBeGreaterThanOrEqual(0)
            ->and($updated->actual_qty)->toBe(52);
    }

    public function test_18_holt_winters_suggests_scaledown_when_waste_exceeds_threshold(): void
    {
        $forecastService = app(VendingForecastService::class);

        $salesHistory = [50, 50, 50, 50, 50, 50, 50];
        $forecast = $forecastService->generate7DayForecast($this->unit->unit_code, $this->item->item_code, $salesHistory, seed: 42);

        // Waste forecast was ~4, actual waste is 10 (>20% breach)
        $updated = $forecastService->recordActualDemandAndWaste($forecast->id, actualDemand: 40, actualWaste: 10);

        expect($updated->suggest_scaledown)->toBeTrue();
    }

    public function test_19_auto_replenishment_po_triggered_when_stock_at_threshold(): void
    {
        $restockService = app(VendingRestockService::class);

        $this->unit->update(['current_stock' => 10]); // below threshold 15

        $po = $restockService->checkAndTriggerAutoPo($this->unit->id, $this->item->item_code);

        expect($po)->not->toBeNull()
            ->and($po->qty_ordered)->toBe(90); // max 100 - 10
    }

    public function test_20_auto_replenishment_po_auto_approves_within_plafon(): void
    {
        $restockService = app(VendingRestockService::class);

        $this->unit->update(['current_stock' => 10]);
        // 90 * 8,000 = 720,000 <= 15,000,000 plafon
        $po = $restockService->checkAndTriggerAutoPo($this->unit->id, $this->item->item_code, dailyPlafonIdr: 5_000_000);

        expect($po->status)->toBe('AUTO_APPROVED');
    }

    public function test_21_auto_replenishment_po_requires_approval_when_exceeding_plafon(): void
    {
        $restockService = app(VendingRestockService::class);

        $this->unit->update(['current_stock' => 10]);
        // 90 * 8,000 = 720,000 > 500,000 plafon
        $po = $restockService->checkAndTriggerAutoPo($this->unit->id, $this->item->item_code, dailyPlafonIdr: 500_000);

        expect($po->status)->toBe('PENDING_APPROVAL');
    }

    public function test_22_restock_task_generation_anti_duplicate(): void
    {
        $restockService = app(VendingRestockService::class);

        $this->unit->update(['current_stock' => 10]);

        $t1 = $restockService->scheduleRestockTask($this->unit->id);
        $t2 = $restockService->scheduleRestockTask($this->unit->id);

        expect($t1->id)->toBe($t2->id);
    }

    public function test_23_restock_task_completion_increments_stock(): void
    {
        $restockService = app(VendingRestockService::class);

        $this->unit->update(['current_stock' => 10]);
        $task = $restockService->scheduleRestockTask($this->unit->id);

        $restockService->completeRestockTask($task->id, 90);

        expect($this->unit->fresh()->current_stock)->toBe(100)
            ->and($task->fresh()->status)->toBe('COMPLETED');
    }

    public function test_24_restocking_route_optimization_exact_tsp_for_small_n(): void
    {
        $restockService = app(VendingRestockService::class);

        // Create 4 units
        $u1 = $this->unit;
        $u2 = VendingUnit::create([
            'unit_code' => 'VND-RTE-02', 'location_name' => 'Loc 2',
            'latitude' => -6195000, 'longitude' => 106825000,
            'current_stock' => 5, 'max_capacity' => 100, 'critical_threshold' => 15,
            'status' => 'active', 'total_sales_count' => 0, 'total_sales_idr' => 0,
        ]);
        $u3 = VendingUnit::create([
            'unit_code' => 'VND-RTE-03', 'location_name' => 'Loc 3',
            'latitude' => -6200000, 'longitude' => 106830000,
            'current_stock' => 2, 'max_capacity' => 100, 'critical_threshold' => 15,
            'status' => 'active', 'total_sales_count' => 0, 'total_sales_idr' => 0,
        ]);

        $route = $restockService->planRestockingRoute([$u1->id, $u2->id, $u3->id]);

        expect($route['algorithm'])->toBe('EXACT_TSP')
            ->and($route['ordered_unit_ids'])->toHaveCount(3)
            ->and($route['certificate']->isValid())->toBeTrue();
    }

    public function test_25_restocking_route_optimization_2opt_for_large_n(): void
    {
        $restockService = app(VendingRestockService::class);

        $unitIds = [$this->unit->id];
        for ($i = 2; $i <= 14; $i++) {
            $u = VendingUnit::create([
                'unit_code' => "VND-LARGE-{$i}", 'location_name' => "Loc {$i}",
                'latitude' => -6190000 + ($i * 1000), 'longitude' => 106820000 + ($i * 1000),
                'current_stock' => 5, 'max_capacity' => 100, 'critical_threshold' => 15,
                'status' => 'active', 'total_sales_count' => 0, 'total_sales_idr' => 0,
            ]);
            $unitIds[] = $u->id;
        }

        $route = $restockService->planRestockingRoute($unitIds);

        expect($route['algorithm'])->toBe('2OPT_HEURISTIC')
            ->and($route['ordered_unit_ids'])->toHaveCount(14)
            ->and($route['certificate']->mstLowerBoundMeters)->toBeGreaterThan(0);
    }

    public function test_26_restocking_route_optimization_generates_certificate(): void
    {
        $restockService = app(VendingRestockService::class);

        $route = $restockService->planRestockingRoute([$this->unit->id]);

        expect($route['certificate'])->not->toBeNull()
            ->and($route['certificate']->isValid())->toBeTrue();
    }

    public function test_27_sensor_fault_reporting_transitions_to_maintenance(): void
    {
        $restockService = app(VendingRestockService::class);

        $updated = $restockService->reportSensorStatus($this->unit->id, [
            'chiller_temp' => 'NORMAL',
            'dispense_arm' => 'JAMMED',
        ]);

        expect($updated->status)->toBe('MAINTENANCE');
    }

    public function test_28_offline_or_maintenance_unit_rejects_dispense(): void
    {
        $dispenseService = app(VendingDispenseService::class);

        $this->unit->update(['status' => 'MAINTENANCE']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Vending unit is not active for dispensing');

        $dispenseService->dispenseItem(
            unitId: $this->unit->id,
            itemCode: $this->item->item_code,
            qty: 1,
            paymentMethod: 'QRIS',
            idempotencyKey: 'vnd:dsp:028'
        );
    }

    public function test_29_vending_transactions_table_is_append_only(): void
    {
        $tx = VendingTransaction::create([
            'tx_code' => 'VTX-APP-029',
            'vending_unit_id' => $this->unit->id,
            'item_code' => $this->item->item_code,
            'qty' => 1,
            'price_idr' => 18_000,
            'cogs_idr' => 8_000,
            'payment_method' => 'QRIS',
            'status' => 'COMPLETED',
            'idempotency_key' => 'vnd:tx:app:029',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('VendingTransaction records are append-only.');

        $tx->update(['price_idr' => 99_000]);
    }

    public function test_30_http_vending_index_requires_authentication(): void
    {
        $response = $this->get('/vending');
        $response->assertRedirect('/login');
    }

    public function test_31_http_vending_renders_for_authorized_vending_operator(): void
    {
        $response = $this->actingAs($this->operator)->get('/vending');
        $response->assertOk();
    }

    public function test_32_http_vending_forbids_unauthorized_user(): void
    {
        $response = $this->actingAs($this->regularCustomer)->get('/vending');
        $response->assertStatus(403);
    }

    public function test_33_http_dispense_requires_idempotency_key_header(): void
    {
        $response = $this->actingAs($this->operator)->postJson('/vending/dispense', [
            'unit_id' => $this->unit->id,
            'item_code' => $this->item->item_code,
            'qty' => 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_34_audit_two_source_passes_with_reconciled_sales_and_inventory(): void
    {
        $exitCode = Artisan::call('vnd:audit');
        expect($exitCode)->toBe(0);
    }

    public function test_35_audit_two_source_detects_corrupted_vending_transaction(): void
    {
        VendingTransaction::create([
            'tx_code' => 'VTX-CORRUPT-035',
            'vending_unit_id' => $this->unit->id,
            'item_code' => $this->item->item_code,
            'qty' => 10,
            'price_idr' => 500_000, // corrupt transaction without unit sales increment
            'cogs_idr' => 80_000,
            'payment_method' => 'QRIS',
            'status' => 'COMPLETED',
            'idempotency_key' => 'vnd:tx:corr:035',
        ]);

        $exitCode = Artisan::call('vnd:audit');
        expect($exitCode)->not->toBe(0);
    }
}
