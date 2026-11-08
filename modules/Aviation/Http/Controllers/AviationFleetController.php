<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Domain\Models\AviationAircraft;
use Modules\Aviation\Domain\Models\AviationMaintenanceVisit;

class AviationFleetController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $query = AviationAircraft::with('operator')->orderBy('registration_mark');

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $aircrafts = $query->paginate(15)->withQueryString();
        $visits = AviationMaintenanceVisit::with('aircraft')->orderByDesc('scheduled_start')->limit(10)->get();

        $stats = [
            'total_aircraft' => AviationAircraft::count(),
            'active' => AviationAircraft::where('status', 'active')->count(),
            'grounded' => AviationAircraft::where('status', 'grounded')->count(),
            'maintenance' => AviationAircraft::where('status', 'maintenance')->count(),
        ];

        return view('aviation::fleet.index', [
            'aircrafts' => $aircrafts,
            'visits' => $visits,
            'stats' => $stats,
            'statusFilter' => $statusFilter,
        ]);
    }
}

