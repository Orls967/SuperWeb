<?php

declare(strict_types=1);

namespace Modules\Mobility\Telematics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mobility\Telematics\Application\Actions\DispatchEmergencyBreakdownTowAction;
use Modules\Mobility\Telematics\Application\Actions\EvaluateCriticalDtcAndGroundVehicleAction;
use Modules\Mobility\Telematics\Application\Actions\ScoreDriverBehaviorAndSafetyIndexAction;
use Modules\Mobility\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Mobility\Telematics\Domain\Models\TelematicsDevice;
use Modules\Mobility\Telematics\Domain\Models\TelematicsTick;

class TelematicsFleetOperationsController extends Controller
{
    public function baselines(Request $request): View
    {
        $baselines = TelematicsBaseline::latest()->paginate(15);
        $totalVehiclesMonitored = TelematicsBaseline::count();

        return view('telematics::telematics.baselines', compact('baselines', 'totalVehiclesMonitored'));
    }

    public function dtcAlerts(Request $request): View
    {
        $criticalTicks = TelematicsTick::whereNotNull('dtc_code')
            ->latest('recorded_at')
            ->paginate(15);

        $groundedDevices = TelematicsDevice::where('status', 'grounded')->get();

        return view('telematics::telematics.dtc_alerts', compact('criticalTicks', 'groundedDevices'));
    }

    public function driverScores(Request $request, ScoreDriverBehaviorAndSafetyIndexAction $action): View
    {
        $vehicleIds = TelematicsTick::distinct()->pluck('vehicle_id')->filter()->take(10);
        $scores = [];

        foreach ($vehicleIds as $vId) {
            $scores[] = $action->handle((int) $vId, 14);
        }

        return view('telematics::telematics.driver_scores', compact('scores'));
    }

    public function emergencyDispatches(Request $request): View
    {
        $groundedDevices = TelematicsDevice::where('status', 'grounded')->take(10)->get();

        return view('telematics::telematics.emergency_dispatches', compact('groundedDevices'));
    }

    public function evaluateDtc(Request $request, EvaluateCriticalDtcAndGroundVehicleAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string',
            'dtc_code' => 'required|string',
        ]);

        $result = $action->handle($validated['device_id'], $validated['dtc_code']);

        $statusMsg = $result['grounded']
            ? "Critical fault {$result['dtc_code']} detected! Vehicle grounded immediately."
            : "Advisory DTC {$result['dtc_code']} logged. Vehicle operating safely.";

        return redirect()->route('telematics.operations.dtc-alerts')->with('success', $statusMsg);
    }

    public function dispatchTow(Request $request, DispatchEmergencyBreakdownTowAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|integer',
            'location' => 'required|string|max:255',
            'reason' => 'required|string|max:255',
            'fee_idr' => 'nullable|integer|min:0',
        ]);

        $fee = (int) ($validated['fee_idr'] ?? 750000);
        $result = $action->handle((int) $validated['vehicle_id'], $validated['location'], $validated['reason'], $fee);

        return redirect()->route('telematics.operations.emergency-dispatches')
            ->with('success', "Tow unit dispatched ({$result['dispatch_id']}) with IDR ".number_format($result['fee_idr']));
    }
}
