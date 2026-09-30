<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Resto\Application\Actions\ApproveStockCountAction;
use Modules\Resto\Application\Actions\CreateStockCountAction;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\StockCount;

class StockCountController extends Controller
{
    public function index(Request $request): View
    {
        $outletId = $request->input('outlet_id') ? (int) $request->input('outlet_id') : null;

        $counts = StockCount::with(['outlet', 'counter', 'approver'])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->latest('id')
            ->paginate(15);

        $outlets = Outlet::where('is_active', true)->get();

        return view('resto::stock_counts.index', [
            'counts' => $counts,
            'outlets' => $outlets,
            'selectedOutletId' => $outletId,
        ]);
    }

    public function create(Request $request, InventoryService $inventoryService): View
    {
        $outletId = (int) ($request->input('outlet_id') ?? Outlet::where('is_active', true)->first()?->id);
        $outlet = Outlet::findOrFail($outletId);
        $outlets = Outlet::where('is_active', true)->get();

        $ingredients = Ingredient::orderBy('name')->get()->map(function ($ing) use ($inventoryService, $outlet) {
            $ing->current_stock = $inventoryService->availableIngredient($ing->id, $outlet->id);

            return $ing;
        });

        return view('resto::stock_counts.create', [
            'outlet' => $outlet,
            'outlets' => $outlets,
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request, CreateStockCountAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'exists:resto_outlets,id'],
            'notes' => ['nullable', 'string', 'max:255'],
            'counted_quantities' => ['required', 'array'],
        ]);

        $stockCount = $action->handle(
            outletId: (int) $validated['outlet_id'],
            countedQuantities: $validated['counted_quantities'],
            counter: $request->user(),
            notes: $validated['notes'] ?? null
        );

        return redirect()->route('resto.stock-counts.show', $stockCount)
            ->with('success', "Stock Opname tanggal {$stockCount->date->format('d/m/Y')} berhasil disimpan dan menunggu approval.");
    }

    public function show(StockCount $stockCount): View
    {
        $stockCount->load(['outlet', 'counter', 'approver', 'lines.ingredient']);

        return view('resto::stock_counts.show', [
            'count' => $stockCount,
        ]);
    }

    public function approve(Request $request, StockCount $stockCount, ApproveStockCountAction $action): RedirectResponse
    {
        $action->handle(
            stockCount: $stockCount,
            approver: $request->user()
        );

        return back()->with('success', 'Stock Opname berhasil disetujui. Penyesuaian stok dan pembukuan selisih persediaan telah diposting.');
    }
}
