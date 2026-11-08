<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Domain\Models\AviationAirport;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Services\AviationExternalFactors;

class AviationOpsController extends Controller
{
    public function __construct(
        private readonly AviationExternalFactors $externalFactors
    ) {
    }

    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $query = AviationFlight::with(['aircraft', 'route', 'origin', 'destination'])->orderBy('departure_time');

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $flights = $query->paginate(15)->withQueryString();

        $stats = [
            'total_today' => AviationFlight::count(),
            'airworthy_cleared' => AviationFlight::where('status', 'airworthy_cleared')->count(),
            'departed' => AviationFlight::where('status', 'departed')->count(),
            'cancelled' => AviationFlight::where('status', 'cancelled')->count(),
            'avtur_price_idr' => $this->externalFactors->getAvturPriceIdrPerLiter(),
        ];

        $airports = AviationAirport::where('status', 'active')->limit(10)->get();

        return view('aviation::ops.index', [
            'flights' => $flights,
            'stats' => $stats,
            'airports' => $airports,
            'statusFilter' => $statusFilter,
        ]);
    }
}

