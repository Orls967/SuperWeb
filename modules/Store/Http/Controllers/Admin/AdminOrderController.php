<?php

declare(strict_types=1);

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Store\Application\Actions\CancelC2cOrderAction;
use Modules\Store\Application\Actions\CancelOrderAction;
use Modules\Store\Application\Actions\ResolveC2cDisputeAction;
use Modules\Store\Application\Actions\UpdateOrderStatusAction;
use Modules\Store\Domain\Models\Order;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly CancelOrderAction $cancelOrderAction,
        private readonly UpdateOrderStatusAction $updateOrderStatusAction,
        private readonly ResolveC2cDisputeAction $resolveC2cDisputeAction,
        private readonly CancelC2cOrderAction $cancelC2cOrderAction
    ) {}

    public function index(Request $request): View
    {
        $query = Order::with(['user', 'items.product'])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $orders = $query->paginate(15)->withQueryString();

        return view('store::admin.orders.index', [
            'orders' => $orders,
            'currentStatus' => $status,
            'currentSearch' => $search,
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items.product', 'paymentIntents']);

        return view('store::admin.orders.show', [
            'order' => $order,
        ]);
    }

    public function ship(Order $order, Request $request): RedirectResponse
    {
        $request->validate([
            'tracking_number' => 'required|string|max:100',
        ]);

        try {
            $this->updateOrderStatusAction->ship($order, $request->input('tracking_number'));

            return redirect()->back()->with('success', "Pesanan {$order->number} berhasil dikirim dengan no resi {$request->input('tracking_number')}.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function complete(Order $order): RedirectResponse
    {
        try {
            $this->updateOrderStatusAction->complete($order);

            return redirect()->back()->with('success', "Pesanan {$order->number} telah diselesaikan.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Admin memutuskan sengketa C2C: cairkan ke penjual atau kembalikan ke pembeli.
     */
    public function resolveDispute(Order $order, Request $request): RedirectResponse
    {
        $request->validate([
            'decision' => 'required|in:capture,release',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $this->resolveC2cDisputeAction->execute(
                $order,
                (string) $request->input('decision'),
                (string) $request->input('note', '')
            );

            $message = $request->input('decision') === 'capture'
                ? "Sengketa {$order->number} diputuskan untuk penjual: dana escrow dicairkan."
                : "Sengketa {$order->number} diputuskan untuk pembeli: dana escrow dikembalikan.";

            return redirect()->back()->with('success', $message);
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Order $order, Request $request): RedirectResponse
    {
        if ($order->isC2c() && $order->status->isEscrowHeld()) {
            try {
                $this->cancelC2cOrderAction->execute($order, (string) $request->input('reason', 'Dibatalkan oleh Admin'));

                return redirect()->back()->with('success', "Transaksi C2C {$order->number} dibatalkan dan dana escrow dikembalikan ke pembeli.");
            } catch (Exception $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        }

        $reason = $request->input('reason', 'Dibatalkan oleh Admin');

        try {
            $this->cancelOrderAction->execute($order, $reason);

            return redirect()->back()->with('success', "Pesanan {$order->number} berhasil dibatalkan dan direfund.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
