<?php

declare(strict_types=1);

namespace Modules\AutoDex\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;

class CarCatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = Car::with('brand')->active();

        // Filter by Search Term
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Filter by Brand Category (jdm, usdm, euro, ev, dll)
        if ($request->filled('category')) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('category', $request->category);
            });
        }

        // Filter by Fuel Type
        if ($request->filled('fuel')) {
            $query->byFuelType($request->fuel);
        }

        // Filter by Body Type
        if ($request->filled('body')) {
            $query->byBodyType($request->body);
        }

        // Filter by Brand ID
        if ($request->filled('brand_id')) {
            $query->byBrand($request->brand_id);
        }

        $cars = $query->latest()->paginate(12)->withQueryString();
        $brands = Brand::active()->orderBy('name')->get();

        // Jika request dari AJAX (Live Search via Alpine.js)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'html' => view('autodex.partials.car-list', compact('cars'))->render(),
                'pagination' => (string) $cars->links(),
            ]);
        }

        return view('autodex.index', compact('cars', 'brands'));
    }

    public function show(Car $car)
    {
        $car->load('brand');

        return view('autodex.show', compact('car'));
    }
}
