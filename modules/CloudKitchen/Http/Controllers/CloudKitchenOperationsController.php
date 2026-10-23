<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CloudKitchen\Application\Actions\AuditThermalSealTamperChainAction;
use Modules\CloudKitchen\Application\Actions\BatchCourierMultiDropDeliveryAction;
use Modules\CloudKitchen\Application\Actions\RouteVirtualBrandOrderToStationAction;
use Modules\CloudKitchen\Application\Actions\SettleVirtualBrandRevenueSplitAction;
use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;

class CloudKitchenOperationsController extends Controller
{
    public function orderRouting(Request $request): View
    {
        $orders = CateringOrder::with('kitchen', 'subscription')->latest()->paginate(15);
        $kitchens = CloudKitchen::where('is_active', true)->get();
        $subscriptions = CateringSubscription::where('status', 'active')->get();

        return view('cloud_kitchen::cloud_kitchen.order_routing', compact('orders', 'kitchens', 'subscriptions'));
    }

    public function courierBatches(Request $request): View
    {
        $readyOrders = CateringOrder::whereIn('status', ['preparing', 'pending'])->get();

        return view('cloud_kitchen::cloud_kitchen.courier_batches', compact('readyOrders'));
    }

    public function revenueSplit(Request $request): View
    {
        $deliveredOrders = CateringOrder::with('kitchen')->where('status', 'delivered')->latest()->paginate(15);

        return view('cloud_kitchen::cloud_kitchen.revenue_split', compact('deliveredOrders'));
    }

    public function thermalAudit(Request $request): View
    {
        $inTransitOrders = CateringOrder::whereIn('status', ['in_transit', 'preparing'])->get();

        return view('cloud_kitchen::cloud_kitchen.thermal_audit', compact('inTransitOrders'));
    }

    public function routeOrder(
        Request $request,
        RouteVirtualBrandOrderToStationAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'subscription_id' => 'required|integer',
            'meal_slot' => 'required|string|max:50',
            'meal_cost_idr' => 'nullable|integer|min:0',
        ]);

        $result = $action->handle(
            (int) $validated['subscription_id'],
            $validated['meal_slot'],
            (int) ($validated['meal_cost_idr'] ?? 45000)
        );

        return redirect()->route('cloud_kitchen.operations.order-routing')
            ->with('success', "Order {$result['order']->order_code} routed to kitchen station {$result['kitchen_code']}.");
    }

    public function batchCouriers(
        Request $request,
        BatchCourierMultiDropDeliveryAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer',
        ]);

        $result = $action->handle(array_map('intval', $validated['order_ids']));

        return redirect()->route('cloud_kitchen.operations.courier-batches')
            ->with('success', "Batched {$result['orders_count']} orders into run {$result['batch_code']} (Payout IDR ".number_format($result['courier_payout_idr']).').');
    }

    public function settleSplit(
        Request $request,
        SettleVirtualBrandRevenueSplitAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'order_id' => 'required|integer',
        ]);

        $result = $action->handle((int) $validated['order_id']);

        return redirect()->route('cloud_kitchen.operations.revenue-split')
            ->with('success', 'Order settled: Kitchen IDR '.number_format($result['kitchen_fee_idr']).', Brand Royalty IDR '.number_format($result['brand_royalty_idr']).', Platform IDR '.number_format($result['platform_fee_idr']));
    }

    public function auditThermal(
        Request $request,
        AuditThermalSealTamperChainAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'seal_barcode' => 'required|string|max:100',
            'temperature_c' => 'required|numeric',
        ]);

        $result = $action->handle(
            (int) $validated['order_id'],
            $validated['seal_barcode'],
            (float) $validated['temperature_c']
        );

        return redirect()->route('cloud_kitchen.operations.thermal-audit')
            ->with('success', "HACCP Thermal Audit: {$result['safety_verdict']} at {$result['temperature_c']}°C (Intact: ".($result['seal_intact'] ? 'YES' : 'NO').')');
    }
}
