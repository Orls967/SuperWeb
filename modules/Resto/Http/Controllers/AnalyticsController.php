<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Resto\Application\Queries\MenuEngineeringQuery;
use Modules\Resto\Application\Services\RestoAnalyticsService;
use Modules\Resto\Domain\Models\Outlet;

class AnalyticsController extends Controller
{
    public function index(
        Request $request,
        MenuEngineeringQuery $menuQuery,
        RestoAnalyticsService $analyticsService
    ): View {
        $outletId = $request->input('outlet_id') ? (int) $request->input('outlet_id') : null;
        $outlets = Outlet::where('is_active', true)->get();

        $selectedOutlet = $outletId ? Outlet::find($outletId) : $outlets->first();
        $targetOutletId = $selectedOutlet?->id;

        $menuEngineering = $menuQuery->execute($outletId, 30);
        $wasteReport = $analyticsService->getWasteReport($outletId, 30);
        $hourlyHeatmap = $analyticsService->getHourlySalesHeatmap($outletId, 30);
        $profitLoss = $analyticsService->getConsolidatedProfitLoss($outletId);
        $forecast = $targetOutletId ? $analyticsService->getIngredientForecast($targetOutletId, 7) : [];

        return view('resto::analytics.index', [
            'outlets' => $outlets,
            'selectedOutletId' => $outletId,
            'selectedOutlet' => $selectedOutlet,
            'menuEngineering' => $menuEngineering,
            'wasteReport' => $wasteReport,
            'hourlyHeatmap' => $hourlyHeatmap,
            'profitLoss' => $profitLoss,
            'forecast' => $forecast,
        ]);
    }
}
