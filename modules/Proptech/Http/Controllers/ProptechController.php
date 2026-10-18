<?php

declare(strict_types=1);

namespace Modules\Proptech\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Proptech\Application\Services\ProptechService;
use Modules\Proptech\Domain\Models\BuildingSensor;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\HvacCommand;
use Modules\Proptech\Domain\Models\PrescriptiveOp;
use Modules\Proptech\Domain\Models\ZoneUtilityBilling;

class ProptechController extends Controller
{
    public function index(Request $request): View
    {
        $zones = BuildingZone::with(['sensors', 'hvacCommands', 'billings'])->latest()->take(10)->get();
        $sensors = BuildingSensor::with('zone')->latest()->take(20)->get();
        $billings = ZoneUtilityBilling::with('zone')->latest()->take(10)->get();
        $hvacCommands = HvacCommand::latest()->take(10)->get();
        $prescriptiveOps = PrescriptiveOp::with('zone')->latest()->take(10)->get();

        $totalCarbonEmissionKg = (float) $billings->sum('carbon_emission_kg');
        $totalUtilityBilledIdr = (int) $billings->sum('total_amount_idr');
        $totalOvertimeBilledIdr = (int) $billings->sum('overtime_ac_amount_idr');

        return view('proptech::proptech.index', compact(
            'zones',
            'sensors',
            'billings',
            'hvacCommands',
            'prescriptiveOps',
            'totalCarbonEmissionKg',
            'totalUtilityBilledIdr',
            'totalOvertimeBilledIdr'
        ));
    }

    public function generateBill(
        Request $request,
        BuildingZone $zone,
        ProptechService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'tenant_id' => 'nullable|integer',
            'billing_date' => 'nullable|date',
            'overtime_ac_hours' => 'nullable|integer|min:0',
        ]);

        $date = ! empty($validated['billing_date']) ? Carbon::parse($validated['billing_date']) : Carbon::now();
        $tenantId = ! empty($validated['tenant_id']) ? (int) $validated['tenant_id'] : null;
        $overtimeHours = isset($validated['overtime_ac_hours']) ? (int) $validated['overtime_ac_hours'] : 0;

        $billing = $service->generateZoneUtilityBilling($zone, $tenantId, $date, $overtimeHours);

        return redirect()->route('proptech.index')->with('success', "Zone utility bill {$billing->billing_code} generated for {$zone->name}.");
    }
}
