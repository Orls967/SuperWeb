<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CloudKitchen\Application\Services\CloudKitchenService;
use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;

class CloudKitchenController extends Controller
{
    public function index(Request $request): View
    {
        $kitchens = CloudKitchen::withCount('orders')->latest()->take(10)->get();
        $subscriptions = CateringSubscription::with('user')->latest()->take(10)->get();
        $orders = CateringOrder::with(['kitchen', 'subscription'])->latest()->take(10)->get();

        return view('cloud_kitchen::cloud_kitchen.index', compact('kitchens', 'subscriptions', 'orders'));
    }

    public function dispatchMeal(
        Request $request,
        CateringSubscription $subscription,
        CloudKitchenService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'meal_slot' => 'required|string|in:breakfast,lunch,dinner',
        ]);

        $order = $service->dispatchMealOrder(
            $subscription,
            $validated['meal_slot'],
            now()
        );

        return redirect()->route('cloud_kitchen.index')->with(
            'success',
            "Meal dispatched successfully! Order: {$order->order_code} (Slot: {$order->meal_slot})"
        );
    }
}
