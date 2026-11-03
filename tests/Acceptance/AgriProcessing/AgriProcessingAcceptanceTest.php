<?php

declare(strict_types=1);

namespace Tests\Acceptance\AgriProcessing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agri\Contracts\HarvestLotSource;
use Modules\Agri\Domain\Models\AgriCollectionBatch;
use Modules\Agri\Domain\Models\AgriContract;
use Modules\Agri\Domain\Models\AgriFarmer;
use Modules\Food\Domain\Models\FoodBatchNutrition;
use Modules\Food\Domain\Models\FoodBrand;
use Modules\Food\Domain\Models\FoodProduct;
use Modules\Manufacturing\Application\Services\AgriProcessing\AgriProcessingService;
use Modules\Manufacturing\Domain\Models\AgriProcessing\MfgAgriBatch;
use Modules\Manufacturing\Domain\Models\AgriProcessing\MfgAgriGenealogyEdge;
use Modules\Shared\Domain\Algorithms\Graph\GenealogyTraversal;
use Modules\Shared\Domain\Algorithms\Lp\LpStatus;
use Tests\TestCase;

class AgriProcessingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $planner;

    protected User $foodQa;

    protected User $brandManager;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->planner = User::factory()->create(['role' => 'planner']);
        $this->foodQa = User::factory()->create(['role' => 'food_qa']);
        $this->brandManager = User::factory()->create(['role' => 'brand_manager']);
        $this->regularUser = User::factory()->create(['role' => 'customer']);
    }

    public function test_rbac_access_agri_processing_and_food_routes(): void
    {
        // 1. /manufacturing/agri-processing (planner, food_qa, admin allowed; customer 403)
        $this->actingAs($this->planner)->get(route('manufacturing.agri-processing.index'))->assertOk();
        $this->actingAs($this->foodQa)->get(route('manufacturing.agri-processing.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('manufacturing.agri-processing.index'))->assertOk();
        $this->actingAs($this->regularUser)->get(route('manufacturing.agri-processing.index'))->assertForbidden();

        // 2. /food/brands (brand_manager, admin allowed; customer 403)
        $this->actingAs($this->brandManager)->get(route('food.brands.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('food.brands.index'))->assertOk();
        $this->actingAs($this->regularUser)->get(route('food.brands.index'))->assertForbidden();
    }

    public function test_lp_simplex_blending_optimal_solution_and_certificate(): void
    {
        $service = app(AgriProcessingService::class);

        // Vektor prompt:
        // Lot A: Rp 9.000/kg, 12% protein (1200 bps), 14% air (1400 bps), cap 600 kg
        // Lot B: Rp 7.500/kg, 9% protein (900 bps), 12% air (1200 bps), cap 700 kg
        // Lot C: Rp 8.000/kg, 11% protein (1100 bps), 16% air (1600 bps), cap 500 kg
        // Target: 1.000 kg, protein >= 10.5% (1050 bps), air <= 14% (1400 bps)
        $lots = [
            ['id' => 'LOT-A', 'cost_per_kg' => 9000, 'protein_bps' => 1200, 'moisture_bps' => 1400, 'available_kg' => 600],
            ['id' => 'LOT-B', 'cost_per_kg' => 7500, 'protein_bps' => 900, 'moisture_bps' => 1200, 'available_kg' => 700],
            ['id' => 'LOT-C', 'cost_per_kg' => 8000, 'protein_bps' => 1100, 'moisture_bps' => 1600, 'available_kg' => 500],
        ];

        $target = [
            'target_weight_kg' => 1000,
            'min_protein_bps' => 1050,
            'max_moisture_bps' => 1400,
        ];

        $solution = $service->optimizeBlending($lots, $target);

        $this->assertEquals(LpStatus::OPTIMAL, $solution->status);
        $this->assertEquals(250, $solution->allocations['LOT-A']);
        $this->assertEquals(375, $solution->allocations['LOT-B']);
        $this->assertEquals(375, $solution->allocations['LOT-C']);
        $this->assertEquals(8_062_500, $solution->totalCostIdr);
        $this->assertTrue($solution->isCertificateValid);
    }

    public function test_lp_infeasible_specification_produces_farkas_certificate(): void
    {
        $service = app(AgriProcessingService::class);

        // Target mustahil: protein >= 15% (1500 bps) padahal nilai tertinggi adalah 12%
        $lots = [
            ['id' => 'LOT-A', 'cost_per_kg' => 9000, 'protein_bps' => 1200, 'moisture_bps' => 1400, 'available_kg' => 600],
            ['id' => 'LOT-B', 'cost_per_kg' => 7500, 'protein_bps' => 900, 'moisture_bps' => 1200, 'available_kg' => 700],
        ];

        $target = [
            'target_weight_kg' => 1000,
            'min_protein_bps' => 1500,
            'max_moisture_bps' => 1400,
        ];

        $solution = $service->optimizeBlending($lots, $target);

        $this->assertEquals(LpStatus::INFEASIBLE, $solution->status);
        $this->assertTrue($solution->isCertificateValid);
        $this->assertNotNull($solution->farkasCertificate);
    }

    public function test_mass_balance_invariant_and_idempotent_harvest_consumption(): void
    {
        $farmer = AgriFarmer::create([
            'farmer_code' => 'FARMER-TEST-01',
            'full_name' => 'Pak Budi Petani',
            'farmer_group_name' => 'Kelompok Tani Berkah',
            'phone_number' => '08123456789',
            'land_area_hectares' => 5.0,
            'primary_commodity' => 'jagung',
        ]);

        $contract = AgriContract::create([
            'contract_number' => 'CTR-AGRI-TEST-01',
            'farmer_id' => $farmer->id,
            'commodity' => 'jagung',
            'planting_date' => now()->subDays(90)->toDateString(),
            'expected_harvest_date' => now()->subDays(5)->toDateString(),
            'target_yield_kg' => 2000.00,
            'guaranteed_floor_price_idr_per_kg' => 8000,
            'status' => 'active',
        ]);

        $harvestLot = AgriCollectionBatch::create([
            'batch_number' => 'AGRI-HARVEST-001',
            'collection_center_id' => 'CC-TALA-01',
            'contract_id' => $contract->id,
            'received_date' => now()->toDateString(),
            'gross_weight_kg' => 1000.00, // 1.000.000 grams
            'grade' => 'GRADE_A',
            'moisture_percentage' => 13.50,
            'buying_price_per_kg' => 8000,
            'gross_payout_idr' => 8000000,
            'advance_deduction_idr' => 0,
            'net_payout_idr' => 8000000,
            'payment_status' => 'paid',
            'destination_unit' => 'MFG_PLANT_01',
        ]);

        $harvestSource = app(HarvestLotSource::class);
        $this->assertEquals(1_000_000, $harvestSource->availableGrams($harvestLot->id));

        // Consume 400.000 grams idempoten
        $key = 'CONS-AGRI-BATCH-001-TEST';
        $harvestSource->consume($harvestLot->id, 400_000, $key);
        $this->assertEquals(600_000, $harvestSource->availableGrams($harvestLot->id));

        // Second consume with exact same key must not deduct again
        $harvestSource->consume($harvestLot->id, 400_000, $key);
        $this->assertEquals(600_000, $harvestSource->availableGrams($harvestLot->id));

        // Processing batch: 400.000g in -> 380.000g output + 20.000g waste (exact mass balance)
        $batch = MfgAgriBatch::create([
            'batch_code' => 'BATCH-MFG-001',
            'target_product_sku' => 'FOOD-CORN-FLOUR-1KG',
            'planned_input_grams' => 400_000,
            'actual_output_grams' => 380_000,
            'waste_grams' => 20_000,
            'status' => 'COMPLETED',
            'protein_bps' => 1100,
            'moisture_bps' => 1350,
            'export_grade' => 'GRADE_A_EXPORT',
        ]);

        $this->assertEquals(
            $batch->planned_input_grams,
            $batch->actual_output_grams + $batch->waste_grams,
            'Invarian neraca massa: input = output + waste'
        );
    }

    public function test_genealogy_graph_recall_forward_backward_and_nutrition_facts(): void
    {
        // 1. Build graph nodes: Agri Lot -> Mfg Batch -> Food Product Pack
        MfgAgriGenealogyEdge::create([
            'parent_entity_type' => 'AGRI_LOT',
            'parent_entity_id' => 'LOT-HARVEST-99',
            'child_entity_type' => 'MFG_BATCH',
            'child_entity_id' => 'BATCH-MILL-77',
            'quantity_grams' => 500_000,
        ]);

        MfgAgriGenealogyEdge::create([
            'parent_entity_type' => 'MFG_BATCH',
            'parent_entity_id' => 'BATCH-MILL-77',
            'child_entity_type' => 'FOOD_PRODUCT_PACK',
            'child_entity_id' => 'PACK-SKU-44',
            'quantity_grams' => 480_000,
        ]);

        $traversal = new GenealogyTraversal;
        $forwardResult = $traversal->traverseForward('AGRI_LOT', 'LOT-HARVEST-99');
        $this->assertTrue($forwardResult->reaches('FOOD_PRODUCT_PACK', 'PACK-SKU-44'));
        $this->assertTrue($forwardResult->certificateVerified);

        $backwardResult = $traversal->traverseBackward('FOOD_PRODUCT_PACK', 'PACK-SKU-44');
        $this->assertTrue($backwardResult->reaches('AGRI_LOT', 'LOT-HARVEST-99'));
        $this->assertTrue($backwardResult->certificateVerified);

        // 2. Nutrition calculation HalfUp 1 decimal in Food module
        $brand = FoodBrand::create([
            'name' => 'Nusantara Agro Prima',
            'code' => 'NAP',
            'description' => 'Produk pangan komoditas lokal',
        ]);

        $product = FoodProduct::create([
            'brand_id' => $brand->id,
            'sku' => 'NAP-CORN-FLOUR-1KG',
            'name' => 'Tepung Jagung Nusantara 1kg',
            'category' => 'Flour',
            'standard_package_grams' => 1000,
        ]);

        $batch = MfgAgriBatch::create([
            'batch_code' => 'BATCH-MILL-77',
            'target_product_sku' => 'NAP-CORN-FLOUR-1KG',
            'planned_input_grams' => 500_000,
            'actual_output_grams' => 480_000,
            'waste_grams' => 20_000,
            'status' => 'COMPLETED',
            'protein_bps' => 1100,
            'moisture_bps' => 1350,
        ]);

        $nutrition = FoodBatchNutrition::create([
            'batch_id' => $batch->id,
            'food_product_id' => $product->id,
            'calories_kcal' => 365,
            'protein_grams' => '9.4',
            'fat_grams' => '1.2',
            'carbs_grams' => '74.3',
            'sodium_mg' => 35,
            'serving_size_grams' => 100,
        ]);

        $this->assertEquals('9.4', (string) $nutrition->protein_grams);
        $this->assertEquals('1.2', (string) $nutrition->fat_grams);
    }

    public function test_food_audit_clean_and_corruption_fixture(): void
    {
        $this->artisan('food:audit')->assertSuccessful();

        // Introduce mass balance corruption: output + waste != input
        $corruptBatch = MfgAgriBatch::create([
            'batch_code' => 'BATCH-CORRUPT-999',
            'target_product_sku' => 'FOOD-CORRUPT-01',
            'planned_input_grams' => 500_000,
            'actual_output_grams' => 400_000,
            'waste_grams' => 50_000, // missing 50,000 grams
            'status' => 'COMPLETED',
            'protein_bps' => 1000,
            'moisture_bps' => 1200,
        ]);

        $this->artisan('food:audit')
            ->assertFailed()
            ->expectsOutputToContain('food.mass_balance_discrepancy');

        $corruptBatch->delete();
        $this->artisan('food:audit')->assertSuccessful();
    }
}
