<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\SmartMeter;

class EgyMeterController extends Controller
{
    public function index(Request $request): View
    {
        $utilityType = $request->query('utility_type');
        $query = SmartMeter::with(['readings' => function ($q) {
            $q->latest('interval_start')->limit(5);
        }]);

        if ($utilityType) {
            $query->where('utility_type', $utilityType);
        }

        $meters = $query->orderBy('meter_code')->paginate(20);

        $metersSummary = [
            'total' => SmartMeter::count(),
            'electricity' => SmartMeter::where('utility_type', 'electricity')->count(),
            'water' => SmartMeter::where('utility_type', 'water')->count(),
            'gas' => SmartMeter::where('utility_type', 'gas')->count(),
            'thermal' => SmartMeter::where('utility_type', 'thermal')->count(),
        ];

        $latestReadings = MeterReading::with('meter')
            ->latest('interval_start')
            ->limit(10)
            ->get();

        return view('egy::meters.index', [
            'meters' => $meters,
            'metersSummary' => $metersSummary,
            'latestReadings' => $latestReadings,
            'currentUtilityType' => $utilityType,
        ]);
    }
}
