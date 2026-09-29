<?php

declare(strict_types=1);

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Store\Application\Actions\ListCarProductAction;
use Modules\Store\Domain\Models\Category;
use Modules\Store\Domain\Models\Product;

class AdminProductController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ListCarProductAction $listCarProductAction
    ) {}

    public function index(Request $request): View
    {
        $query = Product::with(['category', 'productable'])->latest();

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%");
        }

        if ($category = $request->query('category')) {
            $query->where('category_id', $category);
        }

        $products = $query->paginate(15)->withQueryString();
        $categories = Category::all();
        $cars = Car::with('brand')->where('is_active', true)->get();

        return view('store::admin.products.index', [
            'products' => $products,
            'categories' => $categories,
            'cars' => $cars,
            'currentSearch' => $search,
            'currentCategory' => $category,
        ]);
    }

    public function toggleListing(Product $product): RedirectResponse
    {
        $product->update([
            'is_listed' => ! $product->is_listed,
        ]);

        $status = $product->is_listed ? 'dipublikasikan ke Toko' : 'disembunyikan dari Toko';

        return redirect()->back()->with('success', "Produk {$product->name} berhasil {$status}.");
    }

    public function adjustStock(Product $product, Request $request): RedirectResponse
    {
        $request->validate([
            'qty' => 'required|integer',
            'reason' => 'required|string',
            'note' => 'nullable|string|max:255',
        ]);

        $qty = (int) $request->input('qty');
        $reasonStr = (string) $request->input('reason');
        $note = $request->input('note');

        try {
            $reason = StockMovementReason::tryFrom($reasonStr) ?? StockMovementReason::ADJUSTMENT;
            $this->inventoryService->adjust(
                $product->id,
                $qty,
                $reason,
                null,
                null,
                $note ?? 'Penyesuaian stok manual oleh Admin',
                $request->user()->id
            );

            return redirect()->back()->with('success', "Stok {$product->name} berhasil disesuaikan ({$qty}). Stok terkini: {$product->fresh()->cached_stock}");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function createCarListing(Request $request): RedirectResponse
    {
        $request->validate([
            'car_id' => 'required|integer|exists:cars,id',
            'price' => 'required|integer|min:1000000',
            'stock' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $car = Car::with('brand')->findOrFail((int) $request->input('car_id'));

        try {
            $product = $this->listCarProductAction->execute(
                car: $car,
                price: (int) $request->input('price'),
                stock: (int) $request->input('stock'),
                description: $request->input('description')
            );

            return redirect()->back()->with('success', "Mobil {$product->name} berhasil didaftarkan di Store dengan stok {$product->cached_stock} unit!");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
