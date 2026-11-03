<?php

declare(strict_types=1);

namespace Modules\Fashion\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Fashion\Application\Services\FashionSourcingService;
use Modules\Fashion\Domain\Models\FashionCollection;

class FashionCollectionController extends Controller
{
    public function __construct(
        private readonly FashionSourcingService $sourcingService
    ) {}

    public function index(): View
    {
        $collections = FashionCollection::with(['samples', 'buyPlans'])->latest()->limit(20)->get();

        return view('fashion::collections', [
            'collections' => $collections,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'collection_code' => ['required', 'string', 'max:64', 'unique:fsh_collections,collection_code'],
            'name' => ['required', 'string', 'max:128'],
            'season' => ['required', 'string', 'max:32'],
            'year' => ['required', 'integer', 'min:2020', 'max:2050'],
        ]);

        $this->sourcingService->createCollection(
            $validated['collection_code'],
            $validated['name'],
            $validated['season'],
            (int) $validated['year']
        );

        return redirect()->route('fashion.collections.index');
    }

    public function allocate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'buy_plan_id' => ['required', 'integer', 'exists:fsh_buy_plans,id'],
            'sample_id' => ['required', 'integer', 'exists:fsh_samples,id'],
            'po_number' => ['required', 'string', 'max:64', 'unique:fsh_production_orders,po_number'],
            'factory_name' => ['required', 'string', 'max:128'],
            'total_units' => ['required', 'integer', 'min:1'],
            'unit_cost_idr' => ['required', 'integer', 'min:1'],
            'size_weights' => ['required', 'array'],
            'colors' => ['required', 'array'],
        ]);

        $order = $this->sourcingService->allocateAndIssueProductionOrder(
            (int) $validated['buy_plan_id'],
            (int) $validated['sample_id'],
            $validated['po_number'],
            $validated['factory_name'],
            (int) $validated['total_units'],
            (int) $validated['unit_cost_idr'],
            $validated['size_weights'],
            $validated['colors']
        );

        return response()->json([
            'status' => 'OK',
            'order_id' => $order->id,
            'po_number' => $order->po_number,
            'total_ordered_units' => $order->total_ordered_units,
            'allocations_count' => $order->allocations->count(),
            'total_allocated_units' => $order->allocations->sum('allocated_units'),
        ]);
    }
}
