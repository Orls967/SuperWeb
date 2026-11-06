<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Egy\Domain\Models\GenerationAsset;
use Modules\Egy\Domain\Models\GridNode;
use Modules\Egy\Domain\Models\SmartMeter;

class EgyGridController extends Controller
{
    public function dashboard(Request $request): View
    {
        $nodes = GridNode::orderBy('node_code')->get();
        $generationAssets = GenerationAsset::orderBy('asset_code')->get();
        $totalOnlineCapacityMw = (int) $generationAssets->where('status', 'online')->sum('capacity_mw');
        $totalCurrentLoadMw = (int) $nodes->sum('current_load_mw');
        $activeMetersCount = SmartMeter::where('is_active', true)->count();

        // Forecast load calculation (hourly pattern)
        $currentHour = (int) now()->format('H');
        $peakFactor = ($currentHour >= 17 && $currentHour < 22) ? 1.35 : (($currentHour >= 0 && $currentHour < 6) ? 0.65 : 1.0);
        $forecastLoadMw = (int) round($totalCurrentLoadMw * $peakFactor);

        return view('egy::grid.index', [
            'nodes' => $nodes,
            'generationAssets' => $generationAssets,
            'totalOnlineCapacityMw' => $totalOnlineCapacityMw,
            'totalCurrentLoadMw' => $totalCurrentLoadMw,
            'forecastLoadMw' => max($totalCurrentLoadMw, $forecastLoadMw),
            'activeMetersCount' => $activeMetersCount,
            'reserveMarginMw' => max(0, $totalOnlineCapacityMw - $totalCurrentLoadMw),
        ]);
    }
}
