<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Resto\Application\Services\RecipeCostCalculator;
use Modules\Resto\Domain\Enums\RecipeLineType;
use Modules\Resto\Domain\Enums\ServiceStyle;
use Modules\Resto\Domain\Exceptions\RecipeCycleDetected;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\MenuCategory;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\MenuItemOutlet;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\Recipe;
use Modules\Resto\Domain\Models\RecipeLine;

class MenuItemController extends Controller
{
    public function __construct(
        protected RecipeCostCalculator $calculator
    ) {}

    public function index(Request $request): View
    {
        $category = $request->query('category');
        $search = $request->query('search');
        $outletId = $request->query('outlet_id') ? (int) $request->query('outlet_id') : null;

        $query = MenuItem::with(['category', 'recipe.lines.ingredient', 'recipe.lines.subRecipe', 'outletOverrides']);

        if ($category) {
            $query->where('category_id', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('category_id')->orderBy('sort')->paginate(20)->withQueryString();

        // Calculate HPP and margin for each item
        $itemsWithCost = $items->through(function (MenuItem $item) use ($outletId) {
            $costData = $this->calculator->calculateForMenuItem($item, $outletId);

            $sellingPrice = $item->priceForOutlet($outletId);
            $costPerPortion = $costData['cost_per_portion_idr'];
            $marginPercent = (float) $costData['margin_percent']->toFloat();

            $warning = null;
            if ($costData['has_recipe']) {
                if ($costPerPortion > $sellingPrice) {
                    $warning = 'loss'; // HPP > harga jual
                } elseif ($marginPercent < 30.0) {
                    $warning = 'low_margin'; // Margin < 30%
                }
            }

            return [
                'model' => $item,
                'cost_data' => $costData,
                'selling_price' => $sellingPrice,
                'cost_per_portion_idr' => $costPerPortion,
                'margin_percent' => $marginPercent,
                'warning' => $warning,
            ];
        });

        $categories = MenuCategory::orderBy('sort')->get();
        $outlets = Outlet::where('is_active', true)->get();

        return view('resto::menu.index', compact('items', 'itemsWithCost', 'categories', 'outlets', 'outletId'));
    }

    public function create(): View
    {
        $categories = MenuCategory::orderBy('sort')->get();
        $serviceStyles = ServiceStyle::cases();
        $ingredients = Ingredient::orderBy('name')->get();
        $subRecipes = Recipe::whereNotNull('sub_recipe_name')->where('is_active', true)->get();
        $outlets = Outlet::where('is_active', true)->get();

        return view('resto::menu.create', compact('categories', 'serviceStyles', 'ingredients', 'subRecipes', 'outlets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'unique:resto_menu_items,sku'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:resto_menu_categories,id'],
            'service_style' => ['required', 'string'],
            'base_price' => ['required', 'integer', 'min:0'],
            'takeaway_price' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'spice_level' => ['nullable', 'integer', 'min:0', 'max:5'],
            'is_halal_certified' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            // Recipe data
            'has_recipe' => ['nullable', 'boolean'],
            'yield_qty' => ['nullable', 'numeric', 'min:0.000001'],
            'yield_unit' => ['nullable', 'string', 'max:30'],
            'expected_portions' => ['nullable', 'numeric', 'min:0.000001'],
            'waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'instructions' => ['nullable', 'string'],
            'recipe_lines' => ['nullable', 'array'],
            // Outlet price overrides
            'price_overrides' => ['nullable', 'array'],
        ]);

        $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(6);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_halal_certified'] = $request->boolean('is_halal_certified', true);
        $validated['takeaway_price'] = (int) ($validated['takeaway_price'] ?? $validated['base_price']);

        $item = MenuItem::create($validated);

        // Recipe
        if ($request->boolean('has_recipe')) {
            $recipe = Recipe::create([
                'menu_item_id' => $item->id,
                'yield_qty' => number_format((float) ($validated['yield_qty'] ?? 1), 6, '.', ''),
                'yield_unit' => $validated['yield_unit'] ?? 'porsi',
                'expected_portions' => number_format((float) ($validated['expected_portions'] ?? 1), 6, '.', ''),
                'waste_percent' => number_format((float) ($validated['waste_percent'] ?? 0), 2, '.', ''),
                'instructions' => $validated['instructions'] ?? null,
                'version' => 1,
                'is_active' => true,
            ]);

            if (! empty($validated['recipe_lines'])) {
                foreach ($validated['recipe_lines'] as $line) {
                    if (empty($line['qty_base_unit']) || (float) $line['qty_base_unit'] <= 0) {
                        continue;
                    }

                    RecipeLine::create([
                        'recipe_id' => $recipe->id,
                        'line_type' => $line['line_type'] ?? RecipeLineType::INGREDIENT->value,
                        'ingredient_id' => ($line['line_type'] ?? '') === 'ingredient' ? (int) $line['id'] : null,
                        'sub_recipe_id' => ($line['line_type'] ?? '') === 'sub_recipe' ? (int) $line['id'] : null,
                        'qty_base_unit' => number_format((float) $line['qty_base_unit'], 6, '.', ''),
                        'note' => $line['note'] ?? null,
                    ]);
                }
            }
        }

        // Outlet Overrides
        if (! empty($validated['price_overrides'])) {
            foreach ($validated['price_overrides'] as $outletId => $overridePrice) {
                if ($overridePrice !== null && $overridePrice !== '') {
                    MenuItemOutlet::create([
                        'menu_item_id' => $item->id,
                        'outlet_id' => (int) $outletId,
                        'price_override' => (int) $overridePrice,
                        'is_available' => true,
                    ]);
                }
            }
        }

        return redirect()->route('resto.menu.index')
            ->with('success', "Menu {$item->name} berhasil ditambahkan.");
    }

    public function show(MenuItem $item): View
    {
        $item->load(['category', 'recipe.lines.ingredient', 'recipe.lines.subRecipe', 'outletOverrides.outlet']);
        $costData = $this->calculator->calculateForMenuItem($item);

        return view('resto::menu.show', compact('item', 'costData'));
    }

    public function edit(MenuItem $item): View
    {
        $item->load(['category', 'recipe.lines', 'outletOverrides']);
        $categories = MenuCategory::orderBy('sort')->get();
        $serviceStyles = ServiceStyle::cases();
        $ingredients = Ingredient::orderBy('name')->get();
        $subRecipes = Recipe::whereNotNull('sub_recipe_name')->where('is_active', true)->get();
        $outlets = Outlet::where('is_active', true)->get();

        return view('resto::menu.edit', compact('item', 'categories', 'serviceStyles', 'ingredients', 'subRecipes', 'outlets'));
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'unique:resto_menu_items,sku,'.$item->id],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:resto_menu_categories,id'],
            'service_style' => ['required', 'string'],
            'base_price' => ['required', 'integer', 'min:0'],
            'takeaway_price' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'spice_level' => ['nullable', 'integer', 'min:0', 'max:5'],
            'is_halal_certified' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            // Recipe
            'has_recipe' => ['nullable', 'boolean'],
            'yield_qty' => ['nullable', 'numeric', 'min:0.000001'],
            'yield_unit' => ['nullable', 'string', 'max:30'],
            'expected_portions' => ['nullable', 'numeric', 'min:0.000001'],
            'waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'instructions' => ['nullable', 'string'],
            'recipe_lines' => ['nullable', 'array'],
            // Overrides
            'price_overrides' => ['nullable', 'array'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_halal_certified'] = $request->boolean('is_halal_certified');
        $validated['takeaway_price'] = (int) ($validated['takeaway_price'] ?? $validated['base_price']);

        $item->update($validated);

        if ($request->boolean('has_recipe')) {
            $recipe = Recipe::updateOrCreate(
                ['menu_item_id' => $item->id],
                [
                    'yield_qty' => number_format((float) ($validated['yield_qty'] ?? 1), 6, '.', ''),
                    'yield_unit' => $validated['yield_unit'] ?? 'porsi',
                    'expected_portions' => number_format((float) ($validated['expected_portions'] ?? 1), 6, '.', ''),
                    'waste_percent' => number_format((float) ($validated['waste_percent'] ?? 0), 2, '.', ''),
                    'instructions' => $validated['instructions'] ?? null,
                    'is_active' => true,
                ]
            );

            // Rebuild lines
            $recipe->lines()->delete();
            if (! empty($validated['recipe_lines'])) {
                foreach ($validated['recipe_lines'] as $line) {
                    if (empty($line['qty_base_unit']) || (float) $line['qty_base_unit'] <= 0) {
                        continue;
                    }

                    RecipeLine::create([
                        'recipe_id' => $recipe->id,
                        'line_type' => $line['line_type'] ?? RecipeLineType::INGREDIENT->value,
                        'ingredient_id' => ($line['line_type'] ?? '') === 'ingredient' ? (int) $line['id'] : null,
                        'sub_recipe_id' => ($line['line_type'] ?? '') === 'sub_recipe' ? (int) $line['id'] : null,
                        'qty_base_unit' => number_format((float) $line['qty_base_unit'], 6, '.', ''),
                        'note' => $line['note'] ?? null,
                    ]);
                }
            }
        }

        // Update outlet overrides
        if (! empty($validated['price_overrides'])) {
            foreach ($validated['price_overrides'] as $outletId => $overridePrice) {
                MenuItemOutlet::updateOrCreate(
                    ['menu_item_id' => $item->id, 'outlet_id' => (int) $outletId],
                    [
                        'price_override' => $overridePrice !== null && $overridePrice !== '' ? (int) $overridePrice : null,
                        'is_available' => true,
                    ]
                );
            }
        }

        return redirect()->route('resto.menu.index')
            ->with('success', "Menu {$item->name} berhasil diperbarui.");
    }

    /**
     * Real-time HPP calculation endpoint via JSON for Alpine.js builder.
     */
    public function calculateCost(Request $request): JsonResponse
    {
        $outletId = $request->input('outlet_id') ? (int) $request->input('outlet_id') : null;
        $sellingPrice = (int) $request->input('selling_price', 0);
        $expectedPortions = (float) $request->input('expected_portions', 1);
        $wastePercent = (float) $request->input('waste_percent', 0);
        $lines = (array) $request->input('lines', []);

        if ($expectedPortions <= 0) {
            $expectedPortions = 1;
        }

        try {
            $batchCost = BigDecimal::zero();
            $lineOutputs = [];

            foreach ($lines as $line) {
                $lineType = $line['line_type'] ?? 'ingredient';
                $id = (int) ($line['id'] ?? 0);
                $qty = BigDecimal::of((string) ($line['qty_base_unit'] ?? 0));

                if ($qty->isZero()) {
                    continue;
                }

                if ($lineType === 'ingredient' && $id > 0) {
                    $ingredient = Ingredient::find($id);
                    if ($ingredient) {
                        $costRecord = $ingredient->costForOutlet($outletId ?? 0) ?? $ingredient->costs()->first();
                        $unitCost = $costRecord ? BigDecimal::of($costRecord->moving_avg_cost_per_base_unit ?: '0') : BigDecimal::zero();
                        $lineCost = $unitCost->multipliedBy($qty);
                        $batchCost = $batchCost->plus($lineCost);

                        $lineOutputs[] = [
                            'name' => $ingredient->name,
                            'qty' => (string) $qty,
                            'unit' => $ingredient->base_unit->value,
                            'unit_cost' => (float) $unitCost->toFloat(),
                            'line_cost' => (float) $lineCost->toFloat(),
                        ];
                    }
                } elseif ($lineType === 'sub_recipe' && $id > 0) {
                    $subRecipe = Recipe::find($id);
                    if ($subRecipe) {
                        $subResult = $this->calculator->calculateForRecipe($subRecipe, $outletId);
                        $subYield = $subRecipe->yieldQuantity();
                        $unitCost = $subYield->isZero()
                            ? BigDecimal::zero()
                            : $subResult['batch_cost']->dividedBy($subYield, 6, RoundingMode::HalfUp);
                        $lineCost = $unitCost->multipliedBy($qty);
                        $batchCost = $batchCost->plus($lineCost);

                        $lineOutputs[] = [
                            'name' => $subRecipe->sub_recipe_name,
                            'qty' => (string) $qty,
                            'unit' => $subRecipe->yield_unit,
                            'unit_cost' => (float) $unitCost->toFloat(),
                            'line_cost' => (float) $lineCost->toFloat(),
                        ];
                    }
                }
            }

            // Apply waste
            if ($wastePercent > 0) {
                $wasteFactor = BigDecimal::one()->plus(BigDecimal::of((string) $wastePercent)->dividedBy(BigDecimal::of(100), 6, RoundingMode::HalfUp));
                $batchCost = $batchCost->multipliedBy($wasteFactor);
            }

            $costPerPortion = $batchCost->dividedBy(BigDecimal::of((string) $expectedPortions), 6, RoundingMode::HalfUp);
            $costPerPortionIdr = (int) $costPerPortion->toScale(0, RoundingMode::HalfUp)->toInt();

            $marginPercent = 0.0;
            if ($sellingPrice > 0) {
                $marginAmount = BigDecimal::of($sellingPrice)->minus($costPerPortion);
                $marginPercent = (float) $marginAmount->dividedBy(BigDecimal::of($sellingPrice), 4, RoundingMode::HalfUp)
                    ->multipliedBy(BigDecimal::of(100))->toFloat();
            }

            $suggestedPrice = (int) ceil($costPerPortionIdr * 1.5 / 1000) * 1000;

            $warning = null;
            if ($costPerPortionIdr > $sellingPrice && $sellingPrice > 0) {
                $warning = 'loss'; // HPP melebihi harga jual
            } elseif ($marginPercent < 30.0 && $sellingPrice > 0) {
                $warning = 'low_margin'; // Margin di bawah 30%
            }

            return response()->json([
                'success' => true,
                'batch_cost' => (float) $batchCost->toFloat(),
                'cost_per_portion' => (float) $costPerPortion->toFloat(),
                'cost_per_portion_idr' => $costPerPortionIdr,
                'margin_percent' => $marginPercent,
                'suggested_price' => $suggestedPrice,
                'warning' => $warning,
                'lines' => $lineOutputs,
            ]);
        } catch (RecipeCycleDetected $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
