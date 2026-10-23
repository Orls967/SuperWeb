<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Models\StockMovement;

class InventoryMovementExplorerController extends Controller
{
    public function index(Request $request): View
    {
        $query = StockMovement::query()->with('creator');

        if ($request->filled('reason')) {
            $query->where('reason', $request->query('reason'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->query('product_id'));
        }

        $totalMovements = (clone $query)->count();
        $totalInflow = (int) (clone $query)->where('qty', '>', 0)->sum('qty');
        $totalOutflow = abs((int) (clone $query)->where('qty', '<', 0)->sum('qty'));

        $movements = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('inventory::explorer.index', [
            'movements' => $movements,
            'totalMovements' => $totalMovements,
            'totalInflow' => $totalInflow,
            'totalOutflow' => $totalOutflow,
            'reasons' => StockMovementReason::cases(),
            'currentReason' => $request->query('reason'),
        ]);
    }

    public function show(int $id): View
    {
        $movement = StockMovement::with('creator', 'referenceMovement')->findOrFail($id);

        return view('inventory::explorer.show', [
            'movement' => $movement,
        ]);
    }
}
