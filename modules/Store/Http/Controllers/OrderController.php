<?php

declare(strict_types=1);

namespace Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Store\Application\Actions\CancelOrderAction;
use Modules\Store\Application\Actions\UpdateOrderStatusAction;
use Modules\Store\Domain\Models\Order;

class OrderController extends Controller
{
    public function __construct(
        private readonly CancelOrderAction $cancelOrderAction,
        private readonly UpdateOrderStatusAction $updateOrderStatusAction
    ) {}

    public function index(Request $request): View
    {
        $orders = Order::forUser($request->user())
            ->with(['items.product'])
            ->latest()
            ->paginate(10);

        return view('store::orders.index', [
            'orders' => $orders,
        ]);
    }

    public function show(Order $order, Request $request): View
    {
        $user = $request->user();
        if ($order->user_id !== $user->id
            && (int) $order->seller_id !== (int) $user->id
            && ! $user->isAdmin()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $order->load(['items.product.category', 'paymentIntents', 'seller']);

        return view('store::orders.show', [
            'order' => $order,
        ]);
    }

    public function cancel(Order $order, Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($order->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $reason = $request->input('reason', 'Dibatalkan oleh pelanggan');

        try {
            $this->cancelOrderAction->execute($order, $reason);

            return redirect()->route('store.orders.show', $order)
                ->with('success', "Pesanan {$order->number} berhasil dibatalkan dan dana dikembalikan ke dompet.");
        } catch (Exception $e) {
            return redirect()->route('store.orders.show', $order)
                ->with('error', $e->getMessage());
        }
    }

    public function confirm(Order $order, Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($order->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        try {
            $this->updateOrderStatusAction->complete($order);

            return redirect()->route('store.orders.show', $order)
                ->with('success', "Pesanan {$order->number} telah diselesaikan. Terima kasih atas konfirmasinya!");
        } catch (Exception $e) {
            return redirect()->route('store.orders.show', $order)
                ->with('error', $e->getMessage());
        }
    }
}
