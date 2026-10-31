<?php

declare(strict_types=1);

namespace Tests\Acceptance\Culinary;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\CloudKitchen\Application\Services\CateringSubscriptionService;
use Modules\CloudKitchen\Application\Services\KitchenOrderService;
use Modules\CloudKitchen\Application\Services\KitchenStationService;
use Modules\CloudKitchen\Application\Services\RecipeBomService;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;
use Modules\CloudKitchen\Domain\Models\IngredientConsumption;
use Modules\CloudKitchen\Domain\Models\KitchenRecipe;
use Modules\CloudKitchen\Domain\Models\RecipeIngredient;
use Modules\Core\Contracts\SimClockInterface;
use Tests\TestCase;

/**
 * Acceptance Tests: LINI 5 Kuliner - CloudKitchen (§3F Slices 1 & 2).
 *
 * Menguji 35 aturan bisnis CloudKitchen:
 * - Kitchen registration, capacity limits, load balancing
 * - Recipe BOM DAG traversal (B0′.4), cycle detection, verification certificate
 * - Catering subscription, daily quota enforcement, cancellation
 * - Production ingredient deduction (U-4 idempotency, append-only facts)
 * - Multi-drop courier batching & delivery
 * - HACCP thermal seal validation
 * - Hamilton revenue split zero-dust (B0′.1)
 * - HCM payroll integration event (Z9)
 * - HTTP authorization routes (/cloud-kitchen)
 * - Audit two-source ck:audit & corruption fixture
 */
class CloudKitchenAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $chef;

    protected User $manager;

    protected User $admin;

    protected User $regularUser;

    protected CloudKitchen $kitchen;

    protected SimClockInterface $simClock;

    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->simClock = app(SimClockInterface::class);
        $this->ledgerService = app(LedgerService::class);

        $this->chef = User::factory()->create(['name' => 'Chef Juna', 'role' => 'kitchen']);
        $this->manager = User::factory()->create(['name' => 'Outlet Manager Budi', 'role' => 'outlet_manager']);
        $this->admin = User::factory()->create(['name' => 'Catering Admin Siti', 'role' => 'catering_admin']);
        $this->regularUser = User::factory()->create(['name' => 'General Employee Ani', 'role' => 'customer']);

        $this->kitchen = CloudKitchen::create([
            'kitchen_code' => 'CK-SUD-01',
            'name' => 'Sudirman Central Cloud Kitchen',
            'type' => 'central',
            'hourly_capacity' => 50,
            'current_hourly_load' => 0,
            'service_radius_km' => 15.0,
            'is_active' => true,
        ]);
    }

    public function test_01_kitchen_registration_and_capacity_configuration(): void
    {
        $service = app(KitchenStationService::class);
        $newKitchen = $service->registerKitchen([
            'kitchen_code' => 'CK-KUN-02',
            'name' => 'Kuningan Satellite Kitchen',
            'type' => 'satellite',
            'hourly_capacity' => 30,
            'service_radius_km' => 10.0,
        ]);

        expect($newKitchen->kitchen_code)->toBe('CK-KUN-02')
            ->and($newKitchen->hourly_capacity)->toBe(30)
            ->and($newKitchen->current_hourly_load)->toBe(0)
            ->and($newKitchen->is_active)->toBeTrue();
    }

    public function test_02_active_kitchen_lookup_finds_lowest_load_headroom(): void
    {
        $this->kitchen->update(['current_hourly_load' => 20]);

        $kitchen2 = CloudKitchen::create([
            'kitchen_code' => 'CK-TB-03',
            'name' => 'TB Simatupang Kitchen',
            'type' => 'satellite',
            'hourly_capacity' => 50,
            'current_hourly_load' => 5,
            'service_radius_km' => 12.0,
            'is_active' => true,
        ]);

        $service = app(KitchenStationService::class);
        $bestKitchen = $service->findAvailableKitchenWithCapacity();

        expect($bestKitchen->id)->toBe($kitchen2->id)
            ->and($bestKitchen->current_hourly_load)->toBe(5);
    }

    public function test_03_kitchen_capacity_rejection_when_current_load_equals_capacity(): void
    {
        $this->kitchen->update(['current_hourly_load' => 50, 'hourly_capacity' => 50]);

        $service = app(KitchenStationService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No cloud kitchen currently has capacity available.');

        $service->findAvailableKitchenWithCapacity();
    }

    public function test_04_hourly_capacity_decrements_upon_order_completion(): void
    {
        $this->kitchen->update(['current_hourly_load' => 10]);

        $service = app(KitchenStationService::class);
        $service->releaseKitchenCapacity($this->kitchen);

        expect($this->kitchen->fresh()->current_hourly_load)->toBe(9);
    }

    public function test_05_bom_recipe_creation_with_ingredients(): void
    {
        $bomService = app(RecipeBomService::class);

        $recipe = $bomService->createRecipe([
            'recipe_code' => 'RCP-NASI-AYAM',
            'name' => 'Nasi Ayam Lengkuas Signature',
            'brand_name' => 'Dapur Nusantara',
            'selling_price_idr' => 45_000,
            'estimated_prep_minutes' => 25,
            'ingredients' => [
                ['ingredient_code' => 'ING-BERAS', 'quantity_required' => 200, 'unit' => 'gram', 'cost_per_unit_idr' => 25],
                ['ingredient_code' => 'ING-AYAM', 'quantity_required' => 150, 'unit' => 'gram', 'cost_per_unit_idr' => 60],
                ['ingredient_code' => 'ING-BUMBU', 'quantity_required' => 30, 'unit' => 'gram', 'cost_per_unit_idr' => 100],
            ],
        ]);

        expect($recipe->recipe_code)->toBe('RCP-NASI-AYAM')
            ->and($recipe->ingredients)->toHaveCount(3);
    }

    public function test_06_multi_level_nested_recipe_resolution_b04_dag(): void
    {
        $bomService = app(RecipeBomService::class);

        // Sub-recipe: Sambal Terasi
        $subRecipe = $bomService->createRecipe([
            'recipe_code' => 'RCP-SUB-SAMBAL',
            'name' => 'Sambal Terasi Khusus',
            'brand_name' => 'Dapur Nusantara',
            'selling_price_idr' => 5_000,
            'estimated_prep_minutes' => 10,
            'ingredients' => [
                ['ingredient_code' => 'ING-CABE', 'quantity_required' => 30, 'unit' => 'gram', 'cost_per_unit_idr' => 80],
                ['ingredient_code' => 'ING-TERASI', 'quantity_required' => 10, 'unit' => 'gram', 'cost_per_unit_idr' => 50],
            ],
        ]);

        // Main recipe including sub-recipe
        $mainRecipe = $bomService->createRecipe([
            'recipe_code' => 'RCP-BENTO-BOX',
            'name' => 'Paket Bento Nusantara Lengkap',
            'brand_name' => 'Dapur Nusantara',
            'selling_price_idr' => 55_000,
            'estimated_prep_minutes' => 30,
            'ingredients' => [
                ['ingredient_code' => 'ING-NASI', 'quantity_required' => 200, 'unit' => 'gram', 'cost_per_unit_idr' => 20],
                ['ingredient_code' => 'ING-AYAM', 'quantity_required' => 150, 'unit' => 'gram', 'cost_per_unit_idr' => 60],
                ['child_recipe_id' => $subRecipe->id, 'quantity_required' => 1, 'unit' => 'portion', 'cost_per_unit_idr' => 2_900],
            ],
        ]);

        $resolved = $bomService->resolveRawMaterials($mainRecipe->id);

        // Raw materials exploded: ING-NASI, ING-AYAM, ING-CABE, ING-TERASI
        expect($resolved)->toHaveKey('ING-NASI')
            ->and($resolved)->toHaveKey('ING-AYAM')
            ->and($resolved)->toHaveKey('ING-CABE')
            ->and($resolved)->toHaveKey('ING-TERASI');
    }

    public function test_07_recipe_cycle_detection_throws_cycle_exception(): void
    {
        $bomService = app(RecipeBomService::class);

        $r1 = KitchenRecipe::create([
            'recipe_code' => 'RCP-CYCLE-A',
            'name' => 'Cycle Node A',
            'brand_name' => 'Test',
            'selling_price_idr' => 10_000,
            'is_active' => true,
        ]);

        $r2 = KitchenRecipe::create([
            'recipe_code' => 'RCP-CYCLE-B',
            'name' => 'Cycle Node B',
            'brand_name' => 'Test',
            'selling_price_idr' => 10_000,
            'is_active' => true,
        ]);

        RecipeIngredient::create([
            'parent_recipe_id' => $r1->id,
            'child_recipe_id' => $r2->id,
            'quantity_required' => 1,
            'unit' => 'portion',
            'cost_per_unit_idr' => 1000,
        ]);

        RecipeIngredient::create([
            'parent_recipe_id' => $r2->id,
            'child_recipe_id' => $r1->id, // Cycle: A -> B -> A
            'quantity_required' => 1,
            'unit' => 'portion',
            'cost_per_unit_idr' => 1000,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('BOM recipe dependency cycle detected');

        $bomService->resolveRawMaterials($r1->id);
    }

    public function test_08_bom_independent_verification_certificate_generated(): void
    {
        $bomService = app(RecipeBomService::class);

        $recipe = $bomService->createRecipe([
            'recipe_code' => 'RCP-CERT-01',
            'name' => 'Certified Wagyu Rice Bowl',
            'brand_name' => 'Gyu Bowl',
            'selling_price_idr' => 65_000,
            'ingredients' => [
                ['ingredient_code' => 'ING-WAGYU', 'quantity_required' => 100, 'unit' => 'gram', 'cost_per_unit_idr' => 300],
                ['ingredient_code' => 'ING-RICE', 'quantity_required' => 200, 'unit' => 'gram', 'cost_per_unit_idr' => 20],
            ],
        ]);

        $certificate = $bomService->generateBOMCertificate($recipe->id);

        expect($certificate->isValid())->toBeTrue()
            ->and($certificate->totalCostIdr)->toBe(34_000); // 100*300 + 200*20
    }

    public function test_09_meal_order_creation_requires_valid_idempotency_key(): void
    {
        $orderService = app(KitchenOrderService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency key is required.');

        $orderService->orderMeal(
            subscriptionId: 1,
            mealSlot: 'lunch',
            deliveryDate: now()->toDateString(),
            idempotencyKey: ''
        );
    }

    public function test_10_meal_order_same_idempotency_key_returns_cached_result(): void
    {
        $orderService = app(KitchenOrderService::class);
        $sub = $this->createSubscriptionForUser($this->regularUser, 2);
        $recipe = $this->createDefaultRecipe();

        $key = 'ck:ord:idemp:010';
        $order1 = $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), $key, $recipe->id);
        $order2 = $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), $key, $recipe->id);

        expect($order1->id)->toBe($order2->id);
    }

    public function test_11_meal_order_same_key_different_payload_throws_conflict(): void
    {
        $orderService = app(KitchenOrderService::class);
        $sub = $this->createSubscriptionForUser($this->regularUser, 2);
        $recipe = $this->createDefaultRecipe();

        $key = 'ck:ord:idemp:011';
        $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), $key, $recipe->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Idempotency conflict');

        $orderService->orderMeal($sub->id, 'dinner', now()->toDateString(), $key, $recipe->id);
    }

    public function test_12_catering_subscription_creation_with_daily_quota(): void
    {
        $subService = app(CateringSubscriptionService::class);

        $sub = $subService->subscribe([
            'user_id' => $this->regularUser->id,
            'package_name' => 'Executive Lunch 20D',
            'daily_quota' => 2,
            'monthly_fee_idr' => 1_500_000,
            'daily_meal_price_idr' => 37_500,
            'starts_at' => now()->toDateString(),
            'duration_months' => 1,
        ], 'ck:sub:ani:012');

        expect($sub->daily_quota)->toBe(2)
            ->and($sub->status)->toBe('active')
            ->and($sub->monthly_fee_idr)->toBe(1_500_000);
    }

    public function test_13_daily_quota_enforcement_allows_up_to_limit(): void
    {
        $orderService = app(KitchenOrderService::class);
        $sub = $this->createSubscriptionForUser($this->regularUser, 2);
        $recipe = $this->createDefaultRecipe();

        $o1 = $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), 'ck:quota:013a', $recipe->id);
        $o2 = $orderService->orderMeal($sub->id, 'dinner', now()->toDateString(), 'ck:quota:013b', $recipe->id);

        expect($o1->status)->toBe('pending')
            ->and($o2->status)->toBe('pending');
    }

    public function test_14_daily_quota_enforcement_rejects_exceeding_orders(): void
    {
        $orderService = app(KitchenOrderService::class);
        $sub = $this->createSubscriptionForUser($this->regularUser, 1); // quota 1
        $recipe = $this->createDefaultRecipe();

        $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), 'ck:quota:014a', $recipe->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Daily catering meal quota exceeded');

        $orderService->orderMeal($sub->id, 'dinner', now()->toDateString(), 'ck:quota:014b', $recipe->id);
    }

    public function test_15_inactive_subscription_rejects_new_meal_order(): void
    {
        $orderService = app(KitchenOrderService::class);
        $sub = $this->createSubscriptionForUser($this->regularUser, 2);
        $sub->update(['status' => 'cancelled']);
        $recipe = $this->createDefaultRecipe();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Catering subscription is not active');

        $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), 'ck:quota:015', $recipe->id);
    }

    public function test_16_kitchen_production_triggers_inventory_deduction(): void
    {
        $orderService = app(KitchenOrderService::class);
        $sub = $this->createSubscriptionForUser($this->regularUser, 2);
        $recipe = $this->createDefaultRecipe();

        $order = $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), 'ck:prod:016', $recipe->id);
        $orderService->startProduction($order->id);

        expect($order->fresh()->status)->toBe('preparing');

        // Check ingredient consumptions generated
        $consumptions = IngredientConsumption::where('order_id', $order->id)->get();
        expect($consumptions->count())->toBeGreaterThan(0);
    }

    public function test_17_production_deduction_posts_cogs_and_inventory_ledger(): void
    {
        $orderService = app(KitchenOrderService::class);
        $sub = $this->createSubscriptionForUser($this->regularUser, 2);
        $recipe = $this->createDefaultRecipe();

        $order = $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), 'ck:prod:017', $recipe->id);
        $orderService->startProduction($order->id);

        // Verify ledger posting
        $cogsBalance = $this->ledgerService->getCachedBalance('expense:resto:cogs:IDR');
        expect((int) $cogsBalance)->toBeGreaterThan(0);
    }

    public function test_18_ingredient_consumptions_table_is_append_only(): void
    {
        $consumption = IngredientConsumption::create([
            'order_id' => 1,
            'ingredient_code' => 'ING-TEST',
            'quantity_consumed' => 100,
            'unit_cost_idr' => 20,
            'total_cogs_idr' => 2_000,
            'movement_idempotency_key' => 'ck:cns:app:018',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('IngredientConsumption records are append-only.');

        $consumption->update(['quantity_consumed' => 200]);
    }

    public function test_19_multi_drop_courier_batching_groups_ready_orders(): void
    {
        $orderService = app(KitchenOrderService::class);
        $sub = $this->createSubscriptionForUser($this->regularUser, 3);
        $recipe = $this->createDefaultRecipe();

        $o1 = $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), 'ck:bch:019a', $recipe->id);
        $o2 = $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), 'ck:bch:019b', $recipe->id);

        $o1->update(['status' => 'ready_for_pickup']);
        $o2->update(['status' => 'ready_for_pickup']);

        $batchResult = $orderService->createCourierDeliveryBatch([$o1->id, $o2->id], 'Fleet Express Sudirman');

        expect($batchResult['orders_count'])->toBe(2)
            ->and($o1->fresh()->status)->toBe('in_transit')
            ->and($o2->fresh()->status)->toBe('in_transit');
    }

    public function test_20_courier_delivery_completion_marks_order_delivered(): void
    {
        $orderService = app(KitchenOrderService::class);
        $sub = $this->createSubscriptionForUser($this->regularUser, 2);
        $recipe = $this->createDefaultRecipe();

        $order = $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), 'ck:dlv:020', $recipe->id);
        $order->update(['status' => 'in_transit']);

        $orderService->markDelivered($order->id);

        expect($order->fresh()->status)->toBe('delivered');
    }

    public function test_21_haccp_thermal_seal_verification_hot_holding_passed(): void
    {
        $orderService = app(KitchenOrderService::class);
        $result = $orderService->auditHaccpThermalSeal(
            sealBarcode: 'SEAL-20261031-998811',
            tempCelsius: 68.0,
            mealType: 'hot'
        );

        expect($result['is_safe'])->toBeTrue()
            ->and($result['verdict'])->toBe('HACCP_PASSED');
    }

    public function test_22_haccp_thermal_seal_temperature_breach_flagged(): void
    {
        $orderService = app(KitchenOrderService::class);
        $result = $orderService->auditHaccpThermalSeal(
            sealBarcode: 'SEAL-20261031-998811',
            tempCelsius: 48.0, // breached < 60°C
            mealType: 'hot'
        );

        expect($result['is_safe'])->toBeFalse()
            ->and($result['verdict'])->toBe('SAFETY_BREACH_FLAGGED');
    }

    public function test_23_haccp_tampered_barcode_rejected(): void
    {
        $orderService = app(KitchenOrderService::class);
        $result = $orderService->auditHaccpThermalSeal(
            sealBarcode: 'BAD-SEAL',
            tempCelsius: 70.0,
            mealType: 'hot'
        );

        expect($result['is_safe'])->toBeFalse();
    }

    public function test_24_revenue_split_uses_zero_dust_hamilton_apportionment(): void
    {
        $orderService = app(KitchenOrderService::class);

        // 45,001 IDR split 60% operator, 25% brand, 15% platform
        $split = $orderService->calculateRevenueSplit(45_001, [
            'operator' => 6000,
            'brand' => 2500,
            'platform' => 1500,
        ]);

        expect($split['operator'] + $split['brand'] + $split['platform'])->toBe(45_001);
    }

    public function test_25_revenue_split_posts_double_entry_ledger_movements(): void
    {
        $orderService = app(KitchenOrderService::class);
        $sub = $this->createSubscriptionForUser($this->regularUser, 2);
        $recipe = $this->createDefaultRecipe();

        $order = $orderService->orderMeal($sub->id, 'lunch', now()->toDateString(), 'ck:splt:025', $recipe->id);
        $order->update(['status' => 'delivered']);

        $orderService->settleDeliveredOrderRevenue($order->id);

        $catRevenue = $this->ledgerService->getCachedBalance('ckt:catering_revenue:IDR');
        expect((int) $catRevenue)->toBeGreaterThan(0);
    }

    public function test_26_hcm_payroll_event_processes_catering_deduction(): void
    {
        $sub = $this->createSubscriptionForUser($this->regularUser, 2);
        $subService = app(CateringSubscriptionService::class);

        $result = $subService->handlePayrollPostedEvent([
            'employee_id' => $this->regularUser->id,
            'payroll_period' => '2026-10',
            'idempotency_key' => 'hcm:pay:ck:026',
        ]);

        expect($result['deducted_idr'])->toBe($sub->monthly_fee_idr);
    }

    public function test_27_hcm_payroll_deduction_posts_official_payroll_line(): void
    {
        $sub = $this->createSubscriptionForUser($this->regularUser, 2);
        $subService = app(CateringSubscriptionService::class);

        $subService->handlePayrollPostedEvent([
            'employee_id' => $this->regularUser->id,
            'payroll_period' => '2026-10',
            'idempotency_key' => 'hcm:pay:ck:027',
        ]);

        $catRev = $this->ledgerService->getCachedBalance('ckt:catering_revenue:IDR');
        expect((int) $catRev)->toBe($sub->monthly_fee_idr);
    }

    public function test_28_duplicate_payroll_event_skips_duplicate_deduction(): void
    {
        $this->createSubscriptionForUser($this->regularUser, 2);
        $subService = app(CateringSubscriptionService::class);

        $key = 'hcm:pay:ck:028';
        $r1 = $subService->handlePayrollPostedEvent([
            'employee_id' => $this->regularUser->id,
            'payroll_period' => '2026-10',
            'idempotency_key' => $key,
        ]);

        $r2 = $subService->handlePayrollPostedEvent([
            'employee_id' => $this->regularUser->id,
            'payroll_period' => '2026-10',
            'idempotency_key' => $key,
        ]);

        expect($r1['deducted_idr'])->toBe($r2['deducted_idr']);
    }

    public function test_29_http_cloud_kitchen_index_requires_authentication(): void
    {
        $response = $this->get('/cloud-kitchen');
        $response->assertRedirect('/login');
    }

    public function test_30_http_cloud_kitchen_renders_for_authorized_kitchen_role(): void
    {
        $response = $this->actingAs($this->chef)->get('/cloud-kitchen');
        $response->assertOk();
    }

    public function test_31_http_cloud_kitchen_forbids_unauthorized_user(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/cloud-kitchen');
        $response->assertStatus(403);
    }

    public function test_32_http_order_meal_requires_idempotency_key_header(): void
    {
        $sub = $this->createSubscriptionForUser($this->chef, 2);
        $recipe = $this->createDefaultRecipe();

        $response = $this->actingAs($this->chef)->postJson('/cloud-kitchen/orders', [
            'subscription_id' => $sub->id,
            'recipe_id' => $recipe->id,
            'meal_slot' => 'lunch',
            'delivery_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_33_audit_two_source_passes_with_reconciled_kitchen_and_payroll(): void
    {
        $exitCode = Artisan::call('ck:audit');
        expect($exitCode)->toBe(0);
    }

    public function test_34_audit_two_source_detects_corrupted_ingredient_consumption(): void
    {
        IngredientConsumption::create([
            'order_id' => 999,
            'ingredient_code' => 'ING-CORRUPT',
            'quantity_consumed' => 500,
            'unit_cost_idr' => 100,
            'total_cogs_idr' => 50_000,
            'movement_idempotency_key' => 'ck:corrupt:cns:034',
        ]);

        $exitCode = Artisan::call('ck:audit');
        expect($exitCode)->not->toBe(0);
    }

    public function test_35_audit_two_source_detects_corrupted_catering_revenue_balance(): void
    {
        $acc = LedgerAccount::where('code', 'ckt:catering_revenue:IDR')->first();
        if ($acc) {
            $acc->cached_balance = '99999999';
            $acc->save();
        }

        $exitCode = Artisan::call('ck:audit');
        expect($exitCode)->not->toBe(0);
    }

    // Helpers
    protected function createSubscriptionForUser(User $user, int $dailyQuota): CateringSubscription
    {
        return CateringSubscription::create([
            'subscription_code' => 'SUB-ANI-'.rand(100, 999),
            'user_id' => $user->id,
            'package_name' => 'Healthy Daily Meal',
            'daily_quota' => $dailyQuota,
            'used_quota_today' => 0,
            'monthly_fee_idr' => 1_200_000,
            'daily_meal_price_idr' => 40_000,
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ]);
    }

    protected function createDefaultRecipe(): KitchenRecipe
    {
        return KitchenRecipe::create([
            'recipe_code' => 'RCP-DEFAULT-'.rand(100, 999),
            'name' => 'Default Nasi Kotak',
            'brand_name' => 'Dapur Nusantara',
            'selling_price_idr' => 40_000,
            'estimated_prep_minutes' => 20,
            'is_active' => true,
        ]);
    }
}
