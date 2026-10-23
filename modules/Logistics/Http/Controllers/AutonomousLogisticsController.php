<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Logistics\Application\Services\Autonomous\AutonomousLogisticsService;
use Modules\Logistics\Domain\Models\Autonomous\DroneMission;
use Modules\Logistics\Domain\Models\Autonomous\DroneUnit;
use Modules\Logistics\Domain\Models\Autonomous\TempBreachHold;

class AutonomousLogisticsController extends Controller
{
    public function __construct(
        protected AutonomousLogisticsService $service
    ) {}

    public function index(): View
    {
        $breaches = TempBreachHold::orderBy('id', 'desc')->paginate(15, ['*'], 'breaches_page');
        $missions = DroneMission::with('drone')->orderBy('id', 'desc')->paginate(15, ['*'], 'missions_page');
        $drones = DroneUnit::orderBy('id', 'asc')->get();

        $stats = [
            'total_breaches' => TempBreachHold::count(),
            'active_holds' => TempBreachHold::where('status', 'HELD')->count(),
            'total_held_idr' => (int) TempBreachHold::where('status', 'HELD')->sum('hold_amount_idr'),
            'total_drones' => DroneUnit::count(),
            'idle_drones' => DroneUnit::where('status', 'IDLE')->count(),
            'active_missions' => DroneMission::where('status', 'DISPATCHED')->count(),
            'completed_missions' => DroneMission::where('status', 'DELIVERED')->count(),
        ];

        return view('logistics::autonomous.index', [
            'breaches' => $breaches,
            'missions' => $missions,
            'drones' => $drones,
            'stats' => $stats,
        ]);
    }

    public function dispatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'drone_unit_id' => ['required', 'integer', 'exists:lgx_drone_units,id'],
            'shipment_code' => ['required', 'string', 'max:32'],
            'package_weight_kg' => ['required', 'numeric', 'min:0.1', 'max:10.0'],
            'distance_km' => ['required', 'numeric', 'min:0.1', 'max:30.0'],
            'destination_geo_hash' => ['required', 'string', 'max:32'],
        ]);

        $drone = DroneUnit::findOrFail($validated['drone_unit_id']);

        $mission = $this->service->dispatchDroneMission(
            drone: $drone,
            shipmentCode: $validated['shipment_code'],
            packageWeightKg: (float) $validated['package_weight_kg'],
            distanceKm: (float) $validated['distance_km'],
            destinationGeoHash: $validated['destination_geo_hash']
        );

        return redirect()->route('logistics.autonomous.index')
            ->with('success', "Misi drone {$mission->mission_code} berhasil diluncurkan menuju {$mission->destination_geo_hash}.");
    }

    public function deliver(Request $request, DroneMission $mission): RedirectResponse
    {
        $validated = $request->validate([
            'pod_geo_hash' => ['required', 'string', 'max:64'],
        ]);

        $delivered = $this->service->completeDroneDelivery($mission, $validated['pod_geo_hash']);

        return redirect()->route('logistics.autonomous.index')
            ->with('success', "Misi drone {$delivered->mission_code} berhasil diselesaikan dengan bukti POD signature hash.");
    }

    public function release(TempBreachHold $hold): RedirectResponse
    {
        $released = $this->service->releaseBreachHold($hold);

        return redirect()->route('logistics.autonomous.index')
            ->with('success', "Payment hold {$released->breach_code} sebesar Rp ".number_format($released->hold_amount_idr, 0, ',', '.').' berhasil dilepas kembali ke tagihan carrier.');
    }
}
