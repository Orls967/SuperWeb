<?php

declare(strict_types=1);

namespace Modules\Food\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Food\Domain\Models\FoodBrand;

class FoodBrandController extends Controller
{
    public function index(): View
    {
        $brands = FoodBrand::with('products')->orderBy('name')->get();

        return view('food::brands', [
            'brands' => $brands,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:128',
            'code' => 'required|string|max:64|unique:food_brands,code',
            'description' => 'nullable|string|max:500',
        ]);

        FoodBrand::create($data);

        return redirect()->route('food.brands.index')->with('status', 'Merek pangan berhasil didaftarkan');
    }
}
