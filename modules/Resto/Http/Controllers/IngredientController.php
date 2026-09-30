<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Resto\Domain\Enums\BaseUnit;
use Modules\Resto\Domain\Enums\IngredientCategory;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\IngredientCost;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\UnitConversion;

class IngredientController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->query('category');
        $search = $request->query('search');

        $query = Ingredient::with(['conversions', 'costs.outlet']);

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $ingredients = $query->orderBy('category')->orderBy('name')->paginate(20)->withQueryString();
        $categories = IngredientCategory::cases();
        $outlets = Outlet::where('is_active', true)->get();
        $conversions = UnitConversion::with('ingredient')->latest()->get();

        return view('resto::ingredients.index', compact('ingredients', 'categories', 'outlets', 'conversions'));
    }

    public function create(): View
    {
        $categories = IngredientCategory::cases();
        $baseUnits = BaseUnit::cases();
        $outlets = Outlet::where('is_active', true)->get();

        return view('resto::ingredients.create', compact('categories', 'baseUnits', 'outlets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'unique:resto_ingredients,sku'],
            'name' => ['required', 'string', 'max:255'],
            'base_unit' => ['required', 'string'],
            'category' => ['required', 'string'],
            'is_perishable' => ['nullable', 'boolean'],
            'shelf_life_hours' => ['nullable', 'integer', 'min:1'],
            'min_stock_base_unit' => ['required', 'numeric', 'min:0'],
            'costs' => ['nullable', 'array'],
        ]);

        $validated['is_perishable'] = $request->boolean('is_perishable');

        $ingredient = Ingredient::create($validated);

        if (! empty($validated['costs']) && is_array($validated['costs'])) {
            foreach ($validated['costs'] as $outletId => $cost) {
                if ($cost !== null && $cost !== '') {
                    IngredientCost::create([
                        'ingredient_id' => $ingredient->id,
                        'outlet_id' => (int) $outletId,
                        'moving_avg_cost_per_base_unit' => number_format((float) $cost, 6, '.', ''),
                        'last_purchase_cost' => number_format((float) $cost, 6, '.', ''),
                    ]);
                }
            }
        }

        return redirect()->route('resto.ingredients.index')
            ->with('success', "Bahan {$ingredient->name} berhasil ditambahkan.");
    }

    public function edit(Ingredient $ingredient): View
    {
        $categories = IngredientCategory::cases();
        $baseUnits = BaseUnit::cases();
        $outlets = Outlet::where('is_active', true)->get();
        $ingredient->load('costs');

        return view('resto::ingredients.edit', compact('ingredient', 'categories', 'baseUnits', 'outlets'));
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'unique:resto_ingredients,sku,'.$ingredient->id],
            'name' => ['required', 'string', 'max:255'],
            'base_unit' => ['required', 'string'],
            'category' => ['required', 'string'],
            'is_perishable' => ['nullable', 'boolean'],
            'shelf_life_hours' => ['nullable', 'integer', 'min:1'],
            'min_stock_base_unit' => ['required', 'numeric', 'min:0'],
            'costs' => ['nullable', 'array'],
        ]);

        $validated['is_perishable'] = $request->boolean('is_perishable');

        $ingredient->update($validated);

        if (! empty($validated['costs']) && is_array($validated['costs'])) {
            foreach ($validated['costs'] as $outletId => $cost) {
                if ($cost !== null && $cost !== '') {
                    IngredientCost::updateOrCreate(
                        ['ingredient_id' => $ingredient->id, 'outlet_id' => (int) $outletId],
                        [
                            'moving_avg_cost_per_base_unit' => number_format((float) $cost, 6, '.', ''),
                            'last_purchase_cost' => number_format((float) $cost, 6, '.', ''),
                        ]
                    );
                }
            }
        }

        return redirect()->route('resto.ingredients.index')
            ->with('success', "Bahan {$ingredient->name} berhasil diperbarui.");
    }

    public function storeConversion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['nullable', 'exists:resto_ingredients,id'],
            'from_unit' => ['required', 'string', 'max:30'],
            'to_base_factor' => ['required', 'numeric', 'min:0.000001'],
            'label' => ['required', 'string', 'max:100'],
        ]);

        UnitConversion::create([
            'ingredient_id' => $validated['ingredient_id'] ?: null,
            'from_unit' => strtolower($validated['from_unit']),
            'to_base_factor' => number_format((float) $validated['to_base_factor'], 6, '.', ''),
            'label' => $validated['label'],
        ]);

        return redirect()->back()
            ->with('success', 'Konversi satuan berhasil ditambahkan.');
    }
}
