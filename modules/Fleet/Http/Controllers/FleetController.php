<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Fleet\Application\Services\FleetLeasingService;
use Modules\Fleet\Application\Services\FleetSlaMonitorService;
use Modules\Fleet\Domain\Models\FleetContract;
use Modules\Fleet\Domain\Models\FleetContractUnit;
use Modules\Fleet\Domain\Models\FleetSlaLog;

class FleetController extends Controller
{
    public function index(Request $request): View
    {
        $contracts = FleetContract::with('units.vehicle')->latest()->take(10)->get();
        $units = FleetContractUnit::with(['contract', 'vehicle'])->latest()->take(10)->get();
        $slaLogs = FleetSlaLog::with(['contract', 'vehicle'])->latest()->take(10)->get();

        $totalPortfolioIdr = (int) FleetContract::sum('total_lease_value_idr');
        $totalAmortizedIdr = (int) FleetContract::sum('accumulated_amortized_idr');
        $totalUnitsCount = FleetContractUnit::count();
        $activeUnitsCount = FleetContractUnit::where('status', 'assigned')->count();
        $fleetUptimePct = $totalUnitsCount > 0 ? round(($activeUnitsCount / $totalUnitsCount) * 100, 1) : 100.0;
        $totalSlaPenaltiesIdr = (int) FleetSlaLog::sum('penalty_credit_idr');

        return view('fleet::fleet.index', compact(
            'contracts',
            'units',
            'slaLogs',
            'totalPortfolioIdr',
            'totalAmortizedIdr',
            'fleetUptimePct',
            'totalSlaPenaltiesIdr'
        ));
    }

    public function store(Request $request, FleetLeasingService $service): RedirectResponse
    {
        $validated = $request->validate([
            'party_id' => 'required|integer',
            'duration_months' => 'required|integer|min:1|max:60',
            'monthly_rental_idr' => 'required|integer|min:1000000',
            'vehicle_ids' => 'required|array|min:1',
            'vehicle_ids.*' => 'required|integer',
            'security_deposit_idr' => 'nullable|integer|min:0',
        ]);

        $contract = $service->createLeaseContract(
            partyId: (int) $validated['party_id'],
            durationMonths: (int) $validated['duration_months'],
            monthlyRentalIdr: (int) $validated['monthly_rental_idr'],
            vehicleIds: array_map('intval', $validated['vehicle_ids']),
            startDate: Carbon::now(),
            securityDepositIdr: (int) ($validated['security_deposit_idr'] ?? 0)
        );

        return redirect()->route('fleet.index')->with('success', "Kontrak B2B {$contract->contract_number} berhasil dibuat.");
    }

    public function amortize(
        Request $request,
        FleetContract $contract,
        FleetLeasingService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'month_index' => 'required|integer|min:1|max:60',
        ]);

        $service->amortizeMonthly($contract, (int) $validated['month_index']);

        return redirect()->route('fleet.index')->with('success', "Amortisasi sewa bulan {$validated['month_index']} berhasil diposting untuk kontrak {$contract->contract_number}.");
    }

    public function slaClaim(
        Request $request,
        FleetContract $contract,
        FleetSlaMonitorService $slaService
    ): RedirectResponse {
        $validated = $request->validate([
            'vehicle_id' => 'required|integer',
            'incident_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'telematics_grounded_hours' => 'nullable|integer|min:0',
            'workshop_service_hours' => 'nullable|integer|min:0',
        ]);

        $log = $slaService->recordMonthlyDowntime(
            contract: $contract,
            vehicleId: (int) $validated['vehicle_id'],
            incidentMonth: $validated['incident_month'],
            telematicsGroundedHours: (int) ($validated['telematics_grounded_hours'] ?? 0),
            workshopServiceHours: (int) ($validated['workshop_service_hours'] ?? 0)
        );

        $msg = $log->is_compensated
            ? 'Pelanggaran SLA '.$log->breach_hours.' jam dikompensasi kredit nota Rp '.number_format($log->penalty_credit_idr).'.'
            : "Downtime {$log->total_downtime_hours} jam tercatat dalam ambang batas toleransi SLA.";

        return redirect()->route('fleet.index')->with('success', $msg);
    }

    public function returnUnit(
        Request $request,
        FleetContract $contract,
        FleetLeasingService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'vehicle_id' => 'required|integer',
            'condition_notes' => 'nullable|string|max:500',
        ]);

        $service->completeEndOfLeaseReturn(
            contract: $contract,
            vehicleId: (int) $validated['vehicle_id'],
            conditionNotes: $validated['condition_notes'] ?? null
        );

        return redirect()->route('fleet.index')->with('success', "Unit kendaraan #{$validated['vehicle_id']} telah selesai inspeksi akhir sewa dan dikembalikan ke AutoDex.");
    }
}
