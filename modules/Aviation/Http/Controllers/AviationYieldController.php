<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Application\Services\AviationYieldManagementService;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationYieldBucket;
use Modules\Aviation\Http\Requests\CalculateYieldBucketsRequest;
use Modules\Aviation\Http\ViewModels\YieldManagementViewModel;

class AviationYieldController extends Controller
{
    public function __construct(
        protected AviationYieldManagementService $yieldService
    ) {}

    public function index(Request $request): View
    {
        $query = AviationFlight::with(['airline', 'departureAirport', 'arrivalAirport', 'yieldBuckets'])
            ->latest('scheduled_departure');

        if ($request->filled('airline_id')) {
            $query->where('airline_id', $request->integer('airline_id'));
        }

        $flights = $query->paginate(15);

        $metrics = [
            'avg_load_factor' => 78.5,
            'total_revenue_minor_unit' => 450000000000,
            'active_buckets' => AviationYieldBucket::where('is_closed', false)->count(),
        ];

        $vm = new YieldManagementViewModel($flights, $metrics);

        return view('aviation::yield.index', ['vm' => $vm]);
    }

    public function optimize(CalculateYieldBucketsRequest $request): RedirectResponse
    {
        $flightId = $request->integer('flight_id');
        $classes = $request->input('fare_classes', []);

        $res = $this->yieldService->rebalanceFlightBuckets($flightId);

        return redirect()->route('aviation.yield.index')
            ->with('status', sprintf('Hasil ember tarif penerbangan berhasil dioptimalkan (%d bucket terkonfigurasi).', $res['buckets_created_or_updated']));
    }
}
