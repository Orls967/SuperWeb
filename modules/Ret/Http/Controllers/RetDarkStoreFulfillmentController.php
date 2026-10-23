<?php

declare(strict_types=1);

namespace Modules\Ret\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Ret\Application\Actions\AllocateDarkStorePickPackOrderAction;
use Modules\Ret\Domain\Models\RetQuickCommerceOrder;

class RetDarkStoreFulfillmentController extends Controller
{
    public function index(): View
    {
        $orders = RetQuickCommerceOrder::query()->latest()->take(20)->get();

        return view('ret::darkstore.index', compact('orders'));
    }

    public function pickPackView(): View
    {
        $orders = RetQuickCommerceOrder::query()->where('status', 'DELIVERED')->take(15)->get();

        return view('ret::darkstore.pickpack', compact('orders'));
    }

    public function allocate(Request $request, AllocateDarkStorePickPackOrderAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'dark_store_id' => ['required', 'string'],
            'customer_id' => ['required', 'string'],
            'picking_duration_seconds' => ['nullable', 'integer'],
        ]);

        $order = $action->execute($validated);

        return redirect()->back()->with('success', "Order Dark Store Dialokasikan [{$order->order_code}].");
    }
}
