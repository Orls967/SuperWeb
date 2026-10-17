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
use Modules\Proptech\Domain\Models\ZoneUtilityBilling;

class ProptechController extends Controller
{
    public function index(Request $request): View
    {
        $zones = BuildingZone::with(['sensors', 'hvacCommands', 'billings'])->latest()->take(10)->get();
        $sensors = BuildingSensor::with('zone')->latest()->take(10)->get();
        $billings = ZoneUtilityBilling::with('zone')->latest()->take(10)->get();
        $hvacCommands = HvacCommand::latest()->take(10)->get();

        return view('proptech::proptech.index', compact('zones', 'sensors', 'billings', 'hvacCommands'));
    }

    public function generateBill(
        Request $request,
        BuildingZone $zone,
        ProptechService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'tenant_id' => 'nullable|integer',
            'billing_date' => 'nullable|date',
        ]);

        $date = ! empty($validated['billing_date']) ? Carbon::parse($validated['billing_date']) : Carbon::now();
        $tenantId = ! empty($validated['tenant_id']) ? (int) $validated['tenant_id'] : null;

        $billing = $service->generateZoneUtilityBilling($zone, $tenantId, $date);

        return redirect()->route('proptech.index')->with('success', "Zone utility bill {$billing->billing_code} generated for {$zone->name}.");
    }
}
