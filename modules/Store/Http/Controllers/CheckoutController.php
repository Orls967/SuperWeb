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
use Modules\Store\Application\Services\CheckoutService;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $cart = $this->cartService->getOrCreateCart($user);
        $cart->load(['items.product']);

        if ($cart->items->isEmpty()) {
            return redirect()->route('store.cart.index')->with('error', 'Keranjang belanja Anda masih kosong.');
        }

        // Calculate totals
        $subtotal = $cart->subtotal;
        $totalWeight = $cart->total_weight_gram;
        $hasCar = $cart->items->contains(fn ($i) => $i->product?->is_car);

        $shippingFee = $hasCar
            ? 500000
            : max(15000, (int) (ceil($totalWeight / 1000) * 10000));

        $grandTotal = $subtotal + $shippingFee;

        // Wallet account balance
        $wallet = $user->walletAccount('IDR');
        $walletBalance = (int) $wallet->cached_balance;

        return view('store::checkout.index', [
            'cart' => $cart,
            'subtotal' => $subtotal,
            'shippingFee' => $shippingFee,
            'grandTotal' => $grandTotal,
            'walletBalance' => $walletBalance,
        ]);
    }

    public function process(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'pin' => 'required|string|size:6',
        ]);

        $shippingAddress = [
            'name' => $request->input('recipient_name'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'postal_code' => $request->input('postal_code'),
        ];

        $pin = (string) $request->input('pin');

        try {
            $order = $this->checkoutService->checkout(
                $request->user(),
                $shippingAddress,
                $pin
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pesanan berhasil dibuat dan dibayar!',
                    'order' => $order,
                    'redirect_url' => route('store.orders.show', $order),
                ]);
            }

            return redirect()->route('store.orders.show', $order)
                ->with('success', "Pesanan {$order->number} berhasil dibuat dan lunas dibayar!");
        } catch (Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
