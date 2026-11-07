<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Domain\Models\HtlTravelPass;

class HotelTravelPassDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $passes = HtlTravelPass::latest()->paginate(15);
        $totalMembers = HtlTravelPass::count();
        $totalPointsCirculating = (int) HtlTravelPass::sum('pts_balance');
        $totalLiabilityIdr = $totalPointsCirculating * 100;

        $tierCounts = [
            'SILVER' => HtlTravelPass::where('tier', 'SILVER')->count(),
            'GOLD' => HtlTravelPass::where('tier', 'GOLD')->count(),
            'PLATINUM' => HtlTravelPass::where('tier', 'PLATINUM')->count(),
            'BLACK' => HtlTravelPass::where('tier', 'BLACK')->count(),
        ];

        return view('hotel::travel_pass_global_dashboard', [
            'passes' => $passes,
            'totalMembers' => $totalMembers,
            'totalPointsCirculating' => $totalPointsCirculating,
            'totalLiabilityIdr' => $totalLiabilityIdr,
            'tierCounts' => $tierCounts,
        ]);
    }
}
