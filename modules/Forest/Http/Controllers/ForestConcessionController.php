<?php

declare(strict_types=1);

namespace Modules\Forest\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Forest\Domain\Models\ForestConcessionPlot;
use Modules\Forest\Domain\Models\ForestHarvestPermit;
use Modules\Forest\Domain\Models\ForestRestorationPlot;
use Modules\Forest\Domain\Models\ForestTimberLog;
use Modules\Forest\Domain\Models\ForestTimberSale;

final class ForestConcessionController extends Controller
{
    public function index(Request $request): View
    {
        $plots = ForestConcessionPlot::withCount(['treeInventories', 'harvestPermits'])->paginate(15);

        return view('forest::plots', compact('plots'));
    }

    public function plotDetail(Request $request, int $id): View
    {
        $plot = ForestConcessionPlot::with(['treeInventories', 'harvestPermits'])->findOrFail($id);

        return view('forest::plot_detail', compact('plot'));
    }

    public function permits(Request $request): View
    {
        $permits = ForestHarvestPermit::with('plot')->paginate(15);

        return view('forest::permits', compact('permits'));
    }

    public function logs(Request $request): View
    {
        $logs = ForestTimberLog::with(['plot', 'permit'])->paginate(20);

        return view('forest::logs', compact('logs'));
    }

    public function sales(Request $request): View
    {
        $user = $request->user();
        $query = ForestTimberSale::query();

        // IDOR protection: non-admin buyer hanya bisa melihat sales miliknya
        if ($user && $user->role !== 'admin' && $user->role !== 'superadmin') {
            $query->where('buyer_party_ref', $user->id);
        }

        $sales = $query->paginate(20);

        return view('forest::sales', compact('sales'));
    }

    public function restoration(Request $request): View
    {
        $restorations = ForestRestorationPlot::with('plot')->paginate(15);

        return view('forest::restoration', compact('restorations'));
    }
}
