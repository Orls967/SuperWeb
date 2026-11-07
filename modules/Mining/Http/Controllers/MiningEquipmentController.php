<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningEquipmentTelemetry;
use Modules\Mining\Domain\Models\MiningSite;

class MiningEquipmentController extends Controller
{
    public function index(Request $request): View
    {
        $sites = MiningSite::all();
        $equipments = MiningEquipment::with('site')->paginate(20);

        // Perhitungan OEE per alat berat
        $rankedFleet = $equipments->map(function ($eq) {
            $latestTelemetry = MiningEquipmentTelemetry::where('equipment_id', $eq->id)->latest()->first();

            // Availability bps (standby/breakdown reduces availability)
            $availBps = in_array($eq->status, ['operating', 'assigned'], true) ? 9200 : (in_array($eq->status, ['available', 'standby'], true) ? 8000 : 3000);
            // Performance bps
            $perfBps = 9500;
            // Quality bps (payload compliance)
            $qualBps = 9800;

            // OEE = (A * P * Q) / 1e8 scaled to 10000 bps
            $oeeBps = (int) round(($availBps * $perfBps * $qualBps) / 100000000);

            return [
                'equipment' => $eq,
                'availability_bps' => $availBps,
                'performance_bps' => $perfBps,
                'quality_bps' => $qualBps,
                'oee_bps' => $oeeBps,
                'telemetry' => $latestTelemetry,
            ];
        })->sortByDesc('oee_bps')->values();

        $equipment = $equipments;

        return view('mining::equipment.index', compact('sites', 'equipments', 'equipment', 'rankedFleet'));
    }
}
