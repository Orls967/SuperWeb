<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Logistics\Application\Services\Reverse\ReverseLogisticsService;
use Modules\Logistics\Domain\Models\Reverse\ReverseItem;
use Modules\Logistics\Domain\Models\Reverse\ReverseOrder;

class ReverseLogisticsController extends Controller
{
    public function __construct(
        protected ReverseLogisticsService $service
    ) {}

    public function index(): View
    {
        $orders = ReverseOrder::with('items')->orderBy('id', 'desc')->paginate(20);

        $stats = [
            'total_orders' => ReverseOrder::count(),
            'scheduled_orders' => ReverseOrder::where('status', 'SCHEDULED')->count(),
            'processed_orders' => ReverseOrder::where('status', 'PROCESSED')->count(),
            'total_appraised_idr' => (int) ReverseOrder::sum('total_appraised_value_idr'),
            'total_avoided_co2' => (float) ReverseOrder::sum('avoided_emissions_kg_co2'),
            'total_items_kg' => (float) ReverseItem::whereIn('unit_of_measure', ['KG', 'LITER'])->sum('quantity_kg_or_units'),
        ];

        $categoryStats = ReverseItem::selectRaw('item_category, COUNT(*) as count, SUM(quantity_kg_or_units) as total_qty, SUM(subtotal_idr) as total_val')
            ->groupBy('item_category')
            ->get();

        return view('logistics::reverse.index', [
            'orders' => $orders,
            'stats' => $stats,
            'categoryStats' => $categoryStats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_trigger_type' => ['required', 'string', 'max:64'],
            'source_reference_id' => ['required', 'string', 'max:64'],
            'pickup_location' => ['required', 'string', 'max:128'],
            'destination_facility' => ['required', 'string', 'max:128'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.category' => ['required', 'string', 'max:32'],
            'items.*.description' => ['required', 'string', 'max:128'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.uom' => ['required', 'string', 'max:16'],
            'items.*.recycling_target' => ['required', 'string', 'max:64'],
            'items.*.appraised_unit_price_idr' => ['required', 'integer', 'min:0'],
        ]);

        $order = $this->service->triggerReverseOrderFromEvent(
            eventTriggerType: $validated['event_trigger_type'],
            sourceReferenceId: $validated['source_reference_id'],
            pickupLocation: $validated['pickup_location'],
            destinationFacility: $validated['destination_facility'],
            itemsData: $validated['items']
        );

        return redirect()->route('logistics.reverse.index')
            ->with('success', "Reverse order {$order->reverse_code} berhasil dibuat dengan manifest {$order->manifest_number}.");
    }

    public function intake(ReverseOrder $order): RedirectResponse
    {
        $processed = $this->service->processCircularIntake($order);

        return redirect()->route('logistics.reverse.index')
            ->with('success', "Order sirkular {$processed->reverse_code} berhasil diproses ke manufaktur dan dibukukan ke buku besar.");
    }
}
