<?php

declare(strict_types=1);

namespace Tests\Acceptance\Culinary;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\CloudKitchen\Application\Services\KitchenOrderService;
use Modules\CloudKitchen\Application\Services\RecipeBomService;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;
use Modules\CloudKitchen\Domain\Models\IngredientConsumption;
use Modules\CloudKitchen\Domain\Models\KitchenRecipe;
use Modules\CloudKitchen\Domain\Models\RecipeIngredient;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use Modules\Shared\Domain\Math\RouteCertificate;
use Modules\Shared\Domain\Math\RouteCertificateVerifier;
use Modules\Vending\Application\Services\VendingCatalogService;
use Modules\Vending\Application\Services\VendingDispenseService;
use Modules\Vending\Application\Services\VendingRestockService;
use Modules\Vending\Domain\Models\VendingItem;
use Modules\Vending\Domain\Models\VendingTransaction;
use Modules\Vending\Domain\Models\VendingUnit;
use Tests\TestCase;

/**
 * L5.5: Self-Sabotage & Mutation Testing Suite for Culinary MVP.
 * 9 intentional sabotages across CloudKitchen (1-4) and Smart Vending (5-9).
 * Proves that every domain invariant, safety rule, and two-source audit actively catches malicious attacks.
 */
class CulinarySelfSabotageTest extends TestCase
{
    use RefreshDatabase;

    protected User $chef;

    protected User $customer;

    protected CloudKitchen $kitchen;

    protected CateringSubscription $subscription;

    protected VendingUnit $vendingUnit;

    protected VendingItem $vendingItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->chef = User::factory()->create(['name' => 'Chef Sabotage', 'role' => 'kitchen']);
        $this->customer = User::factory()->create(['name' => 'Customer Sabotage', 'role' => 'customer']);

        $this->kitchen = CloudKitchen::create([
            'kitchen_code' => 'CK-SAB-01',
            'name' => 'Sabotage Test Ghost Kitchen',
            'type' => 'central',
            'hourly_capacity' => 10,
            'current_hourly_load' => 0,
            'service_radius_km' => 10.0,
            'is_active' => true,
        ]);

        $this->subscription = CateringSubscription::create([
            'subscription_code' => 'SUB-SAB-001',
            'user_id' => $this->customer->id,
            'package_name' => 'Sabotage Lunch 1D',
            'daily_quota' => 1,
            'used_quota_today' => 0,
            'monthly_fee_idr' => 1_000_000,
            'daily_meal_price_idr' => 45_000,
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ]);

        $this->vendingUnit = VendingUnit::create([
            'unit_code' => 'VND-SAB-01',
            'location_name' => 'Sabotage Test Floor',
            'latitude' => -6.2000,
            'longitude' => 106.8000,
            'current_stock' => 10,
            'max_capacity' => 50,
            'critical_threshold' => 5,
            'status' => 'active',
            'total_sales_count' => 0,
            'total_sales_idr' => 0,
        ]);

        $this->vendingItem = VendingItem::create([
            'item_code' => 'ITM-SAB-01',
            'name' => 'Sabotage Soda',
            'category' => 'beverage',
            'base_price_idr' => 15_000,
            'cost_price_idr' => 5_000,
            'shelf_life_hours' => 48,
        ]);
    }

    // =========================================================================
    // CLOUD KITCHEN SABOTAGES (1-4)
    // =========================================================================

    public function test_sabotage_01_daily_catering_quota_bypass_is_caught(): void
    {
        $orderService = app(KitchenOrderService::class);

        // Order 1: within daily quota 1
        $orderService->orderMeal(
            subscriptionId: $this->subscription->id,
            mealSlot: 'lunch',
            deliveryDate: now()->toDateString(),
            idempotencyKey: 'ck:sab:01:order1'
        );

        // Order 2: attempts to exceed daily quota of 1
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Daily catering meal quota exceeded');

        $orderService->orderMeal(
            subscriptionId: $this->subscription->id,
            mealSlot: 'lunch',
            deliveryDate: now()->toDateString(),
            idempotencyKey: 'ck:sab:01:order2'
        );
    }

    public function test_sabotage_02_corrupted_ingredient_consumption_caught_by_ck_audit(): void
    {
        $orderService = app(KitchenOrderService::class);
        $order = $orderService->orderMeal(
            subscriptionId: $this->subscription->id,
            mealSlot: 'lunch',
            deliveryDate: now()->toDateString(),
            idempotencyKey: 'ck:sab:02:ord'
        );

        // Inject corrupted unlinked ingredient consumption record
        IngredientConsumption::create([
            'order_id' => $order->id,
            'ingredient_code' => 'ING-CORRUPT-SAB',
            'quantity_consumed' => 1000,
            'unit_cost_idr' => 100,
            'total_cogs_idr' => 100_000,
            'movement_idempotency_key' => 'ck:corrupt:sab:002',
        ]);

        $exitCode = Artisan::call('ck:audit');
        $this->assertNotEquals(0, $exitCode, 'ck:audit must detect unauthorized/corrupted ingredient consumption.');
    }

    public function test_sabotage_03_catering_revenue_ledger_tampering_caught_by_ck_audit(): void
    {
        $revenueAcc = LedgerAccount::where('code', 'ckt:catering_revenue:IDR')->first();
        $this->assertNotNull($revenueAcc);

        // Tamper cached balance directly in database without operational backing
        $revenueAcc->cached_balance = '999999999';
        $revenueAcc->save();

        $exitCode = Artisan::call('ck:audit');
        $this->assertNotEquals(0, $exitCode, 'ck:audit must catch revenue ledger cached balance tampering.');
    }

    public function test_sabotage_04_bom_recipe_cycle_injection_is_caught(): void
    {
        $bomService = app(RecipeBomService::class);

        $nodeA = KitchenRecipe::create([
            'recipe_code' => 'RCP-SAB-CYC-A',
            'name' => 'Cycle Node Alpha',
            'brand_name' => 'Malicious Recipe',
            'selling_price_idr' => 20_000,
            'is_active' => true,
        ]);

        $nodeB = KitchenRecipe::create([
            'recipe_code' => 'RCP-SAB-CYC-B',
            'name' => 'Cycle Node Beta',
            'brand_name' => 'Malicious Recipe',
            'selling_price_idr' => 20_000,
            'is_active' => true,
        ]);

        // A requires B
        RecipeIngredient::create([
            'parent_recipe_id' => $nodeA->id,
            'child_recipe_id' => $nodeB->id,
            'quantity_required' => 1,
            'unit' => 'portion',
            'cost_per_unit_idr' => 5000,
        ]);

        // B requires A (creates direct cycle A -> B -> A)
        RecipeIngredient::create([
            'parent_recipe_id' => $nodeB->id,
            'child_recipe_id' => $nodeA->id,
            'quantity_required' => 1,
            'unit' => 'portion',
            'cost_per_unit_idr' => 5000,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('BOM recipe dependency cycle detected');

        $bomService->resolveRawMaterials($nodeA->id);
    }

    // =========================================================================
    // SMART VENDING SABOTAGES (5-9)
    // =========================================================================

    public function test_sabotage_05_vending_stock_negative_dispense_is_caught(): void
    {
        $dispenseService = app(VendingDispenseService::class);

        // Deplete stock to 0
        $this->vendingUnit->current_stock = 0;
        $this->vendingUnit->save();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient stock in vending unit');

        $dispenseService->dispenseItem(
            unitId: $this->vendingUnit->id,
            itemCode: $this->vendingItem->item_code,
            qty: 1,
            paymentMethod: 'QRIS',
            idempotencyKey: 'vnd:sab:05:neg'
        );
    }

    public function test_sabotage_06_vending_transaction_price_corruption_caught_by_vnd_audit(): void
    {
        // Inject unrecorded completed transaction without updating unit total_sales_idr
        VendingTransaction::create([
            'tx_code' => 'VTX-SAB-CORRUPT-06',
            'vending_unit_id' => $this->vendingUnit->id,
            'item_code' => $this->vendingItem->item_code,
            'qty' => 5,
            'price_idr' => 75_000,
            'cogs_idr' => 25_000,
            'payment_method' => 'QRIS',
            'status' => 'COMPLETED',
            'idempotency_key' => 'vnd:sab:06:tx',
        ]);

        $exitCode = Artisan::call('vnd:audit');
        $this->assertNotEquals(0, $exitCode, 'vnd:audit must catch transaction sum vs unit total_sales_idr discrepancy.');
    }

    public function test_sabotage_07_dynamic_markdown_floor_safety_is_preserved(): void
    {
        $catalogService = app(VendingCatalogService::class);

        // Test extreme zero hours until expiry (flash clearance)
        $pricing = $catalogService->calculateDynamicPrice($this->vendingItem->item_code, 0);

        $this->assertSame(50, $pricing['discount_pct']);
        $this->assertSame('FLASH_CLEARANCE_50', $pricing['tier']);
        // Base price was 15,000 -> 50% discount = 7,500 IDR
        $this->assertSame(7_500, $pricing['final_price_idr']);
        $this->assertGreaterThanOrEqual(1000, $pricing['final_price_idr']);
    }

    public function test_sabotage_08_restock_route_tour_corruption_caught_by_certificate_verifier(): void
    {
        // Construct corrupted tour with duplicate vertex / missing vertex
        $corruptedTour = [0, 1, 1, 0]; // Invalid tour: duplicates vertex 1, length 4 for 3 vertices
        $distanceMatrix = [
            [0, 100, 200],
            [100, 0, 150],
            [200, 150, 0],
        ];

        $corruptedCert = new RouteCertificate(
            n: 3,
            tour: $corruptedTour,
            cost: 350,
            lowerBound: 250,
            gapBps: 4000,
            isExact: false,
            dist: $distanceMatrix
        );

        $this->expectException(InvalidCertificateException::class);
        $this->expectExceptionMessage('Duplicate visit to node');

        RouteCertificateVerifier::verify($corruptedCert);
    }

    public function test_sabotage_09_auto_po_over_plafon_manual_approval_bypass_is_caught(): void
    {
        $restockService = app(VendingRestockService::class);

        // Drop stock below critical threshold (5)
        $this->vendingUnit->current_stock = 4;
        $this->vendingUnit->save();

        // Trigger Auto-PO with total cost exceeding daily plafon
        // Needed qty: 50 - 4 = 46. Cost per unit = 500,000 IDR -> Total = 23,000,000 IDR
        // Daily plafon = 15,000,000 IDR
        $po = $restockService->checkAndTriggerAutoPo(
            unitId: $this->vendingUnit->id,
            itemCode: $this->vendingItem->item_code,
            costPerUnitIdr: 500_000,
            dailyPlafonIdr: 15_000_000
        );

        $this->assertNotNull($po);
        $this->assertSame('PENDING_APPROVAL', $po->status, 'Over-plafon PO must require manual approval.');
        $this->assertNotSame('AUTO_APPROVED', $po->status, 'Over-plafon PO must NEVER bypass to AUTO_APPROVED.');
    }
}
