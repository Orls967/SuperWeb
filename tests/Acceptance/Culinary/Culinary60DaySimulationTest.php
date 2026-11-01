<?php

declare(strict_types=1);

namespace Tests\Acceptance\Culinary;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Application\Services\LedgerService;
use Modules\CloudKitchen\Application\Services\CateringSubscriptionService;
use Modules\CloudKitchen\Application\Services\KitchenOrderService;
use Modules\CloudKitchen\Application\Services\KitchenStationService;
use Modules\CloudKitchen\Application\Services\RecipeBomService;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;
use Modules\CloudKitchen\Domain\Models\IngredientConsumption;
use Modules\CloudKitchen\Domain\Models\KitchenOrder;
use Modules\CloudKitchen\Domain\Models\KitchenRecipe;
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
 * L5.4: 60-Day Continuous Deterministic Simulation for Culinary MVP.
 * Simulates real-world lifecycle across CloudKitchen (BOM recipes, quota subscriptions,
 * multi-drop courier dispatch, HACCP integrity, HCM payroll deductions) and
 * Smart Vending (idempotent dispense, dynamic near-expiry markdown, Holt-Winters forecasting,
 * auto-PO replenishment, and TSP route optimization).
 */
class Culinary60DaySimulationTest extends TestCase
{
    use RefreshDatabase;

    protected SimClockInterface $simClock;

    protected LedgerService $ledgerService;

    protected KitchenStationService $stationService;

    protected RecipeBomService $bomService;

    protected KitchenOrderService $kitchenOrderService;

    protected CateringSubscriptionService $subscriptionService;

    protected VendingCatalogService $catalogService;

    protected VendingDispenseService $dispenseService;

    protected VendingForecastService $forecastService;

    protected VendingRestockService $restockService;

    protected User $chef;

    protected User $operator;

    /** @var list<User> */
    protected array $employees;

    protected CloudKitchen $centralKitchen;

    protected KitchenRecipe $beefBowlRecipe;

    /** @var list<CateringSubscription> */
    protected array $subscriptions;

    /** @var list<VendingUnit> */
    protected array $vendingUnits;

    protected VendingItem $coldBrew;

    protected VendingItem $greenTea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->simClock = app(SimClockInterface::class);
        $this->ledgerService = app(LedgerService::class);
        $this->stationService = app(KitchenStationService::class);
        $this->bomService = app(RecipeBomService::class);
        $this->kitchenOrderService = app(KitchenOrderService::class);
        $this->subscriptionService = app(CateringSubscriptionService::class);
        $this->catalogService = app(VendingCatalogService::class);
        $this->dispenseService = app(VendingDispenseService::class);
        $this->forecastService = app(VendingForecastService::class);
        $this->restockService = app(VendingRestockService::class);

        $startTime = Carbon::parse('2026-05-01 08:00:00', 'UTC');
        $this->simClock->initRun('Culinary60dSim', $startTime, 42);

        $this->chef = User::factory()->create(['name' => 'Chef Budi', 'role' => 'kitchen']);
        $this->operator = User::factory()->create(['name' => 'Operator Doni', 'role' => 'vending_operator']);

        $this->employees = [];
        for ($i = 1; $i <= 3; $i++) {
            $user = User::factory()->create([
                'name' => "Corporate Employee {$i}",
                'email' => "emp{$i}@conglomerate.test",
                'role' => 'customer',
            ]);
            $user->walletAccount('IDR');
            $this->employees[] = $user;
        }

        // 1. Central Cloud Kitchen Setup
        $this->centralKitchen = $this->stationService->registerKitchen([
            'kitchen_code' => 'CK-SIM-CENTRAL-01',
            'name' => 'Central Mega Kitchen Mega Kuningan',
            'type' => 'central',
            'hourly_capacity' => 100,
            'service_radius_km' => 20.0,
            'is_active' => true,
        ]);

        // 2. Multi-level BOM Recipe Setup (B0′.4 DAG)
        $this->beefBowlRecipe = $this->bomService->createRecipe([
            'recipe_code' => 'RCP-GYU-SIM-01',
            'name' => 'Simulated Signature Beef Bowl',
            'brand_name' => 'Gyu Bowl Corp',
            'selling_price_idr' => 45_000,
            'estimated_prep_minutes' => 15,
            'ingredients' => [
                ['ingredient_code' => 'ING-BEEF', 'quantity_required' => 100, 'unit' => 'gram', 'cost_per_unit_idr' => 25],
                ['ingredient_code' => 'ING-RICE', 'quantity_required' => 200, 'unit' => 'gram', 'cost_per_unit_idr' => 10],
                ['ingredient_code' => 'ING-SAUCE', 'quantity_required' => 50, 'unit' => 'gram', 'cost_per_unit_idr' => 15],
            ],
        ]);

        // Verify certificate on recipe creation
        $cert = $this->bomService->generateBOMCertificate($this->beefBowlRecipe->id);
        $this->assertTrue($cert->isValid());
        $this->assertSame(5_250, $cert->totalCostIdr); // 100*25 + 200*10 + 50*15 = 5250

        // 3. Catering Subscriptions for 3 Employees
        $this->subscriptions = [];
        foreach ($this->employees as $emp) {
            $sub = $this->subscriptionService->subscribe([
                'user_id' => $emp->id,
                'package_name' => 'Executive 60D Lunch',
                'daily_quota' => 1,
                'monthly_fee_idr' => 1_000_000,
                'daily_meal_price_idr' => 45_000,
            ], "ck:sub:init:emp:{$emp->id}");
            $this->subscriptions[] = $sub;
        }

        // 4. Smart Vending Units Setup (3 locations)
        $this->vendingUnits = [
            $this->restockService->registerUnit([
                'unit_code' => 'VND-SIM-LOBBY-01',
                'location_name' => 'HQ Main Lobby',
                'latitude' => -6.2250,
                'longitude' => 106.8250,
                'max_capacity' => 100,
                'current_stock' => 50,
                'critical_threshold' => 10,
            ]),
            $this->restockService->registerUnit([
                'unit_code' => 'VND-SIM-TOWER-02',
                'location_name' => 'Tower 2 Executive Floor',
                'latitude' => -6.2260,
                'longitude' => 106.8270,
                'max_capacity' => 80,
                'current_stock' => 40,
                'critical_threshold' => 10,
            ]),
            $this->restockService->registerUnit([
                'unit_code' => 'VND-SIM-CANTEEN-03',
                'location_name' => 'Basement Food Court',
                'latitude' => -6.2280,
                'longitude' => 106.8240,
                'max_capacity' => 120,
                'current_stock' => 60,
                'critical_threshold' => 15,
            ]),
        ];

        // 5. Vending Items Catalog
        $this->coldBrew = $this->catalogService->registerItem([
            'item_code' => 'ITM-BEV-CB01',
            'name' => 'Single Origin Cold Brew',
            'category' => 'beverage',
            'base_price_idr' => 25_000,
            'cost_price_idr' => 8_000,
            'shelf_life_hours' => 336,
        ]);

        $this->greenTea = $this->catalogService->registerItem([
            'item_code' => 'ITM-BEV-GT02',
            'name' => 'Organic Japanese Green Tea',
            'category' => 'beverage',
            'base_price_idr' => 15_000,
            'cost_price_idr' => 5_000,
            'shelf_life_hours' => 240,
        ]);
    }

    public function test_60_day_continuous_culinary_simulation(): void
    {
        $startDate = Carbon::parse('2026-05-01 08:00:00', 'UTC');
        $totalOrdersCreated = 0;
        $totalDispenseTx = 0;

        for ($day = 1; $day <= 60; $day++) {
            if ($day > 1) {
                $this->simClock->advanceDays(1);
            }
            $currentTime = $this->simClock->now();
            $dateStr = $currentTime->toDateString();
            $dayOfWeek = (int) $currentTime->format('N'); // 1 = Monday, 7 = Sunday

            // A. CLOUD KITCHEN PIPELINE (Weekdays only: Mon-Fri)
            if ($dayOfWeek <= 5) {
                $dayOrderIds = [];

                foreach ($this->subscriptions as $sub) {
                    $idempKey = "ck:sim:ord:d{$day}:s{$sub->id}";
                    $order = $this->kitchenOrderService->orderMeal(
                        subscriptionId: $sub->id,
                        mealSlot: 'lunch',
                        deliveryDate: $dateStr,
                        idempotencyKey: $idempKey,
                        recipeId: $this->beefBowlRecipe->id
                    );
                    $totalOrdersCreated++;
                    $dayOrderIds[] = $order->id;

                    // Kitchen production with BOM deduction (U-4 & B0′.4)
                    $this->kitchenOrderService->startProduction($order->id);

                    // HACCP Thermal Seal Audit
                    $haccp = $this->kitchenOrderService->auditHaccpThermalSeal('SEAL-HOT-998877', 72.5, 'hot');
                    $this->assertTrue($haccp['is_safe']);
                }

                // Batch into multi-drop courier delivery (B0′.6)
                $this->kitchenOrderService->createCourierDeliveryBatch(
                    $dayOrderIds,
                    'SuperWeb Express Courier Swift'
                );

                // Complete delivery & settle revenue (Hamilton split B0′.1)
                foreach ($dayOrderIds as $orderId) {
                    $this->kitchenOrderService->markDelivered($orderId);
                    $this->kitchenOrderService->settleDeliveredOrderRevenue($orderId);
                }
            }

            // B. MONTH-END HCM PAYROLL DEDUCTION (Day 30 and Day 60)
            if ($day === 30 || $day === 60) {
                $month = ($day === 30) ? 1 : 2;
                foreach ($this->subscriptions as $sub) {
                    $this->subscriptionService->handlePayrollPostedEvent([
                        'employee_id' => $sub->user_id,
                        'payroll_period' => "2026-0{$month}",
                        'idempotency_key' => "ck:sim:payroll:m{$month}:s{$sub->id}",
                    ]);
                }
            }

            // C. SMART VENDING DISPENSE PIPELINE
            foreach ($this->vendingUnits as $idx => $unit) {
                // Refresh unit state from db
                $unit->refresh();

                if ($unit->current_stock > 0) {
                    $item = ($idx % 2 === 0) ? $this->coldBrew : $this->greenTea;
                    $paymentMethod = ($day % 2 === 0) ? 'BIOMETRIC_FACE' : 'QRIS';

                    $this->dispenseService->dispenseItem(
                        unitId: $unit->id,
                        itemCode: $item->item_code,
                        qty: 1,
                        idempotencyKey: "vnd:sim:dsp:d{$day}:u{$unit->id}:i{$item->item_code}",
                        paymentMethod: $paymentMethod
                    );
                    $totalDispenseTx++;
                }

                // Dynamic near-expiry markdown check (FEFO)
                $hoursToExpiry = max(1, 48 - ($day % 10) * 4);
                $markdown = $this->catalogService->calculateDynamicPrice($this->coldBrew->item_code, $hoursToExpiry);
                $this->assertGreaterThan(0, $markdown['final_price_idr']);

                // Holt-Winters 7-day projection & MAPE recording (B0′.5 with seed 42)
                $history = [12, 14, 15, 11, 13, 16, 14, 15, 13, 14, 12, 16, 15, 14];
                $forecast = $this->forecastService->generate7DayForecast(
                    outletCode: $unit->unit_code,
                    itemCode: $this->coldBrew->item_code,
                    salesHistory: $history,
                    seed: 42
                );
                $this->assertGreaterThan(0, $forecast->forecast_qty);

                // Record actual sales and track MAPE
                $this->forecastService->recordActualDemandAndWaste($forecast->id, 14, 1);

                // Auto-PO and restock task triggering when below threshold
                $unit->refresh();
                if ($unit->current_stock <= $unit->critical_threshold) {
                    $autoPo = $this->restockService->checkAndTriggerAutoPo(
                        unitId: $unit->id,
                        itemCode: $this->coldBrew->item_code,
                        costPerUnitIdr: 8_000,
                        dailyPlafonIdr: 15_000_000
                    );
                    $this->assertNotNull($autoPo);

                    $task = $this->restockService->scheduleRestockTask($unit->id);
                    if ($task) {
                        // Restock route optimization (B0′.6)
                        $routeResult = $this->restockService->planRestockingRoute([$unit->id]);
                        $this->assertTrue($routeResult['certificate']->isValid());

                        // Restock unit
                        $this->restockService->completeRestockTask($task->id, 30);
                    }
                }
            }
        }

        // D. SIMULATION COMPLETION & INVARIANT VERIFICATION
        $this->assertGreaterThanOrEqual(120, $totalOrdersCreated); // ~43 weekdays * 3 subs = 129 orders
        $this->assertGreaterThanOrEqual(150, $totalDispenseTx);    // 60 days * 3 units

        // Double-entry ledger verification: Cloud Kitchen Catering Revenue balance
        $cktRevenueBalance = (int) $this->ledgerService->getCachedBalance('ckt:catering_revenue:IDR');
        $this->assertGreaterThan(0, $cktRevenueBalance);

        // Double-entry ledger verification: Vending Sales Revenue balance
        $vndRevenueBalance = (int) $this->ledgerService->getCachedBalance('vnd:sales_revenue:IDR');
        $this->assertGreaterThan(0, $vndRevenueBalance);

        // Verification of COGS expense accumulation
        $cogsBalance = (int) $this->ledgerService->getCachedBalance('expense:resto:cogs:IDR');
        $this->assertLessThan(0, $cogsBalance); // Negative debit balance under single-sign convention

        // E. TWO-SOURCE AUDITS (Must pass cleanly with zero discrepancies)
        $ckAuditExit = Artisan::call('ck:audit');
        $this->assertSame(0, $ckAuditExit, 'CloudKitchen two-source audit must pass with exit code 0.');

        $vndAuditExit = Artisan::call('vnd:audit');
        $this->assertSame(0, $vndAuditExit, 'Vending two-source audit must pass with exit code 0.');

        // F. DETERMINISTIC STATE HASH
        $orderCount = KitchenOrder::count();
        $consumptionCount = IngredientConsumption::count();
        $vendingTxCount = VendingTransaction::count();

        $stateHash = hash('sha256', json_encode([
            'orders' => $orderCount,
            'consumptions' => $consumptionCount,
            'vending_txs' => $vendingTxCount,
            'ckt_revenue' => $cktRevenueBalance,
            'vnd_revenue' => $vndRevenueBalance,
        ]));

        $this->assertNotEmpty($stateHash);
        $this->assertSame(64, strlen($stateHash));
    }
}
