<?php

declare(strict_types=1);

namespace Modules\Ret\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Ret\Application\Actions\CheckoutOmnichannelUnifiedCartAction;
use Modules\Ret\Application\Actions\ProcessClickAndCollectLockerPickupAction;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetOrder;

class RetOmnichannelOrderController extends Controller
{
    public function index(): View
    {
        $orders = RetOrder::query()->latest()->take(20)->get();
        $channels = RetChannel::query()->latest()->take(10)->get();

        return view('ret::orders.index', compact('orders', 'channels'));
    }

    public function show(string $id): View
    {
        $order = RetOrder::findOrFail($id);

        return view('ret::orders.show', compact('order'));
    }

    public function checkout(Request $request, CheckoutOmnichannelUnifiedCartAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'channel_id' => ['required', 'string'],
            'customer_id' => ['required', 'string'],
            'items' => ['required', 'array'],
        ]);

        $order = $action->execute($validated);

        return redirect()->back()->with('success', "Pesanan Berhasil Dibuat [{$order->order_number}]: Rp ".number_format($order->total_amount_minor, 0, ',', '.'));
    }

    public function pickup(Request $request, ProcessClickAndCollectLockerPickupAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string'],
            'locker_id' => ['required', 'string'],
            'otp_token' => ['required', 'string'],
        ]);

        $result = $action->execute($validated['order_number'], $validated['locker_id'], $validated['otp_token']);

        return redirect()->back()->with('success', "Pesanan [{$result['order_number']}] berhasil diambil di locker {$result['locker_id']}.");
    }
}
