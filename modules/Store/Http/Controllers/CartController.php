<?php

declare(strict_types=1);

namespace Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Store\Application\Services\CartService;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $cart = $this->cartService->getOrCreateCart($user);
        $cart->load(['items.product.category']);

        if ($request->wantsJson()) {
            return response()->json([
                'items' => $cart->items,
                'subtotal' => $cart->subtotal,
                'total_weight_gram' => $cart->total_weight_gram,
                'items_count' => $cart->items_count,
            ]);
        }

        return view('store::cart.index', [
            'cart' => $cart,
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['count' => 0, 'subtotal' => 0]);
        }

        $cart = $this->cartService->getOrCreateCart($user);
        $cart->load('items');

        return response()->json([
            'count' => $cart->items_count,
            'subtotal' => $cart->subtotal,
            'formatted_subtotal' => 'Rp '.number_format($cart->subtotal, 0, ',', '.'),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:store_products,id',
            'qty' => 'nullable|integer|min:1',
        ]);

        $productId = (int) $request->input('product_id');
        $qty = (int) ($request->input('qty', 1));

        try {
            $item = $this->cartService->addItem($request->user(), $productId, $qty);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produk berhasil ditambahkan ke keranjang.',
                    'item' => $item,
                ]);
            }

            return redirect()->back()->with('success', 'Produk berhasil ditambahkan ke keranjang.');
        } catch (Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, int $productId): JsonResponse|RedirectResponse
    {
        $request->validate([
            'qty' => 'required|integer|min:0',
        ]);

        $qty = (int) $request->input('qty');

        try {
            $item = $this->cartService->updateItem($request->user(), $productId, $qty);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'item' => $item,
                ]);
            }

            return redirect()->route('store.cart.index')->with('success', 'Keranjang berhasil diperbarui.');
        } catch (Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->route('store.cart.index')->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request, int $productId): JsonResponse|RedirectResponse
    {
        $this->cartService->removeItem($request->user(), $productId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Item dihapus dari keranjang.',
            ]);
        }

        return redirect()->route('store.cart.index')->with('success', 'Item berhasil dihapus dari keranjang.');
    }
}
