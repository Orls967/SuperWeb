<?php

declare(strict_types=1);

namespace Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Store\Domain\Models\Category;
use Modules\Store\Domain\Models\Product;

class CatalogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $query = Product::with(['category', 'productable'])
            ->where('is_listed', true);

        // Search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($categorySlug = $request->query('category')) {
            $query->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // Type filter (cars, parts, general)
        if ($type = $request->query('type')) {
            if ($type === 'cars') {
                $query->where('is_car', true);
            } elseif ($type === 'parts') {
                $query->where('productable_type', 'serve_sparepart');
            } elseif ($type === 'items') {
                $query->where('is_car', false)->where('productable_type', '!=', 'serve_sparepart');
            }
        }

        // Sort
        $sort = $request->query('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'stock_desc' => $query->orderBy('cached_stock', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $products = $query->paginate(12)->withQueryString();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'products' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'total' => $products->total(),
                ],
            ]);
        }

        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();

        return view('store::catalog.index', [
            'products' => $products,
            'categories' => $categories,
            'currentSearch' => $search,
            'currentCategory' => $categorySlug,
            'currentSort' => $sort,
            'currentType' => $type,
        ]);
    }

    public function show(string $slug): View
    {
        $product = Product::with(['category', 'productable'])
            ->where('slug', $slug)
            ->where('is_listed', true)
            ->firstOrFail();

        $relatedProducts = Product::where('is_listed', true)
            ->where('id', '!=', $product->id)
            ->where('is_car', $product->is_car)
            ->limit(4)
            ->get();

        return view('store::catalog.show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}
