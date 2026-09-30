<?php

declare(strict_types=1);

namespace Modules\Resto\tests\Feature;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Resto\Application\Services\RecipeCostCalculator;
use Modules\Resto\Domain\Enums\BaseUnit;
use Modules\Resto\Domain\Enums\IngredientCategory;
use Modules\Resto\Domain\Enums\OutletType;
use Modules\Resto\Domain\Enums\RecipeLineType;
use Modules\Resto\Domain\Enums\ServiceStyle;
use Modules\Resto\Domain\Exceptions\RecipeCycleDetected;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\IngredientCost;
use Modules\Resto\Domain\Models\MenuCategory;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\MenuItemOutlet;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\Recipe;
use Modules\Resto\Domain\Models\RecipeLine;
use Modules\Resto\Domain\Models\UnitConversion;
use Tests\TestCase;

class RestoFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_unit_conversion_converts_to_and_from_base_unit_accurately(): void
    {
        $convKg = UnitConversion::create([
            'from_unit' => 'kg',
            'to_base_factor' => '1000.000000',
            'label' => 'Kilogram ke Gram',
        ]);

        $baseGrams = $convKg->toBase('2.75');
        $this->assertTrue($baseGrams->isEqualTo(BigDecimal::of('2750')));

        $backKg = $convKg->fromBase('2750');
        $this->assertTrue($backKg->isEqualTo(BigDecimal::of('2.75')));

        $convIkat = UnitConversion::create([
            'from_unit' => 'ikat',
            'to_base_factor' => '250.000000',
            'label' => 'Ikat ke Gram',
        ]);

        $this->assertTrue($convIkat->toBase('4')->isEqualTo(BigDecimal::of('1000')));
    }

    public function test_multi_level_recipe_calculates_hpp_per_portion_with_decimal_precision(): void
    {
        $outlet = Outlet::create([
            'code' => 'TEST-01',
            'name' => 'Outlet Test',
            'type' => OutletType::OUTLET,
            'address' => 'Jl. Test',
            'city' => 'Banjarmasin',
        ]);

        // Ingredients
        $cabe = Ingredient::create([
            'sku' => 'ING-CABE',
            'name' => 'Cabai Merah',
            'base_unit' => BaseUnit::GRAM,
            'category' => IngredientCategory::BUMBU,
            'min_stock_base_unit' => '100',
        ]);
        IngredientCost::create([
            'ingredient_id' => $cabe->id,
            'outlet_id' => $outlet->id,
            'moving_avg_cost_per_base_unit' => '50.000000', // Rp 50/g = Rp 50.000/kg
        ]);

        $bawang = Ingredient::create([
            'sku' => 'ING-BAWANG',
            'name' => 'Bawang Merah',
            'base_unit' => BaseUnit::GRAM,
            'category' => IngredientCategory::BUMBU,
            'min_stock_base_unit' => '100',
        ]);
        IngredientCost::create([
            'ingredient_id' => $bawang->id,
            'outlet_id' => $outlet->id,
            'moving_avg_cost_per_base_unit' => '40.000000', // Rp 40/g = Rp 40.000/kg
        ]);

        $daging = Ingredient::create([
            'sku' => 'ING-DAGING',
            'name' => 'Daging Sapi',
            'base_unit' => BaseUnit::GRAM,
            'category' => IngredientCategory::PROTEIN,
            'min_stock_base_unit' => '100',
        ]);
        IngredientCost::create([
            'ingredient_id' => $daging->id,
            'outlet_id' => $outlet->id,
            'moving_avg_cost_per_base_unit' => '140.000000', // Rp 140/g = Rp 140.000/kg
        ]);

        // Sub-Recipe: Bumbu Dasar (yield: 500 gram)
        // 300g cabe @ Rp 50 = Rp 15.000
        // 200g bawang @ Rp 40 = Rp 8.000
        // Total sub-recipe batch = Rp 23.000 for 500g => cost per gram = Rp 46/g
        $subRecipe = Recipe::create([
            'sub_recipe_name' => 'Bumbu Dasar Merah Test',
            'yield_qty' => '500.000000',
            'yield_unit' => 'gram',
            'expected_portions' => '5.000000',
            'waste_percent' => '0.00',
            'is_active' => true,
        ]);
        RecipeLine::create([
            'recipe_id' => $subRecipe->id,
            'line_type' => RecipeLineType::INGREDIENT,
            'ingredient_id' => $cabe->id,
            'qty_base_unit' => '300.000000',
        ]);
        RecipeLine::create([
            'recipe_id' => $subRecipe->id,
            'line_type' => RecipeLineType::INGREDIENT,
            'ingredient_id' => $bawang->id,
            'qty_base_unit' => '200.000000',
        ]);

        // Main Recipe: Rendang (yield: 10 portions)
        // 1000g daging @ Rp 140 = Rp 140.000
        // 250g Bumbu Dasar @ Rp 46 = Rp 11.500
        // Waste = 5% => total batch cost = Rp (140.000 + 11.500) * 1.05 = Rp 151.500 * 1.05 = Rp 159.075
        // Cost per portion for 10 portions = Rp 15.907,5
        $cat = MenuCategory::create(['name' => 'Lauk Daging', 'sort' => 1]);
        $menuItem = MenuItem::create([
            'category_id' => $cat->id,
            'sku' => 'MNU-TEST-RENDANG',
            'name' => 'Rendang Daging Test',
            'slug' => 'rendang-daging-test',
            'service_style' => ServiceStyle::HIDANG,
            'base_price' => 25000,
        ]);

        $mainRecipe = Recipe::create([
            'menu_item_id' => $menuItem->id,
            'yield_qty' => '10.000000',
            'yield_unit' => 'porsi',
            'expected_portions' => '10.000000',
            'waste_percent' => '5.00',
            'is_active' => true,
        ]);
        RecipeLine::create([
            'recipe_id' => $mainRecipe->id,
            'line_type' => RecipeLineType::INGREDIENT,
            'ingredient_id' => $daging->id,
            'qty_base_unit' => '1000.000000',
        ]);
        RecipeLine::create([
            'recipe_id' => $mainRecipe->id,
            'line_type' => RecipeLineType::SUB_RECIPE,
            'sub_recipe_id' => $subRecipe->id,
            'qty_base_unit' => '250.000000',
        ]);

        $calculator = app(RecipeCostCalculator::class);
        $result = $calculator->calculateForMenuItem($menuItem, $outlet->id);

        $this->assertTrue($result['has_recipe']);
        $this->assertEquals(159075, (int) $result['batch_cost']->toScale(0, RoundingMode::HalfUp)->toInt());
        $this->assertEquals(15908, $result['cost_per_portion_idr']);
        $this->assertGreaterThan(30.0, (float) $result['margin_percent']->toFloat());
    }

    public function test_circular_sub_recipe_is_detected_and_throws_exception(): void
    {
        $recipeA = Recipe::create([
            'sub_recipe_name' => 'Resep A',
            'yield_qty' => '100.000000',
            'yield_unit' => 'gram',
            'is_active' => true,
        ]);

        $recipeB = Recipe::create([
            'sub_recipe_name' => 'Resep B',
            'yield_qty' => '100.000000',
            'yield_unit' => 'gram',
            'is_active' => true,
        ]);

        // Recipe A points to Recipe B
        RecipeLine::create([
            'recipe_id' => $recipeA->id,
            'line_type' => RecipeLineType::SUB_RECIPE,
            'sub_recipe_id' => $recipeB->id,
            'qty_base_unit' => '50.000000',
        ]);

        // Recipe B points back to Recipe A (Circular!)
        RecipeLine::create([
            'recipe_id' => $recipeB->id,
            'line_type' => RecipeLineType::SUB_RECIPE,
            'sub_recipe_id' => $recipeA->id,
            'qty_base_unit' => '50.000000',
        ]);

        $calculator = app(RecipeCostCalculator::class);

        $this->expectException(RecipeCycleDetected::class);
        $calculator->calculateForRecipe($recipeA);
    }

    public function test_price_per_outlet_override_and_availability(): void
    {
        $outlet1 = Outlet::create(['code' => 'OUT-1', 'name' => 'Outlet 1', 'address' => 'A', 'city' => 'B']);
        $outlet2 = Outlet::create(['code' => 'OUT-2', 'name' => 'Outlet 2', 'address' => 'A', 'city' => 'B']);

        $cat = MenuCategory::create(['name' => 'Lauk', 'sort' => 1]);
        $item = MenuItem::create([
            'category_id' => $cat->id,
            'sku' => 'MNU-TEST',
            'name' => 'Ayam Goreng Test',
            'slug' => 'ayam-goreng-test',
            'service_style' => ServiceStyle::HIDANG,
            'base_price' => 20000,
            'takeaway_price' => 22000,
            'is_active' => true,
        ]);

        // Override price for outlet 2
        MenuItemOutlet::create([
            'menu_item_id' => $item->id,
            'outlet_id' => $outlet2->id,
            'price_override' => 25000,
            'is_available' => true,
        ]);

        $this->assertEquals(20000, $item->priceForOutlet($outlet1->id));
        $this->assertEquals(25000, $item->priceForOutlet($outlet2->id));
        $this->assertEquals(22000, $item->priceForOutlet(null, isTakeaway: true));

        // When item is inactive
        $item->update(['is_active' => false]);
        $this->assertFalse($item->isAvailableForOutlet($outlet1->id));
        $this->assertFalse($item->isAvailableForOutlet($outlet2->id));

        // When item is active, but outlet 2 marked unavailable
        $item->update(['is_active' => true]);
        MenuItemOutlet::where('menu_item_id', $item->id)
            ->where('outlet_id', $outlet2->id)
            ->update(['is_available' => false]);

        $this->assertTrue($item->isAvailableForOutlet($outlet1->id));
        $this->assertFalse($item->isAvailableForOutlet($outlet2->id));
    }

    public function test_http_crud_and_calculate_cost_endpoint(): void
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin.resto@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $cat = MenuCategory::create(['name' => 'Minuman', 'sort' => 1]);
        $ing = Ingredient::create([
            'sku' => 'ING-TEST-TEH',
            'name' => 'Teh Test',
            'base_unit' => BaseUnit::GRAM,
            'category' => IngredientCategory::MINUMAN,
            'min_stock_base_unit' => '100',
        ]);

        // 1. Index pages
        $this->actingAs($admin)->get(route('resto.menu.index'))->assertStatus(200);
        $this->actingAs($admin)->get(route('resto.ingredients.index'))->assertStatus(200);
        $this->actingAs($admin)->get(route('resto.outlets.index'))->assertStatus(200);

        // 2. Real-time Calculate Cost JSON endpoint
        $response = $this->actingAs($admin)->postJson(route('resto.menu.calculate-cost'), [
            'selling_price' => 10000,
            'expected_portions' => 1,
            'waste_percent' => 0,
            'lines' => [
                [
                    'line_type' => 'ingredient',
                    'id' => $ing->id,
                    'qty_base_unit' => 20,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
