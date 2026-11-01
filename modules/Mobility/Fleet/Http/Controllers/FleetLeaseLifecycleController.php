<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mobility\Fleet\Application\Actions\BillExcessMileageAndWearTearAction;
use Modules\Mobility\Fleet\Application\Actions\OptimizeFleetTcoReplacementScheduleAction;
use Modules\Mobility\Fleet\Application\Actions\OriginatePsak73LeaseWithRouAndPvLiabilityAction;
use Modules\Mobility\Fleet\Application\Actions\RecognizeMonthlyLeaseInterestAndRouAmortizationAction;
use Modules\Mobility\Fleet\Domain\Models\FleetContract;
use Modules\Mobility\Fleet\Domain\Models\FleetContractUnit;

class FleetLeaseLifecycleController extends Controller
{
    public function psak73Leases(Request $request): View
    {
        $contracts = FleetContract::with('units')->latest()->paginate(15);
        $totalPvLiability = FleetContract::sum('present_value_liability_idr');

        return view('fleet::fleet.psak73_leases', compact('contracts', 'totalPvLiability'));
    }

    public function amortizationSchedule(Request $request): View
    {
        $contracts = FleetContract::where('status', 'active')->get();

        return view('fleet::fleet.amortization_schedule', compact('contracts'));
    }

    public function excessMileage(Request $request): View
    {
        $units = FleetContractUnit::with('contract')->latest()->paginate(20);

        return view('fleet::fleet.excess_mileage', compact('units'));
    }

    public function tcoOptimization(Request $request): View
    {
        $contracts = FleetContract::with('units')->where('status', 'active')->get();

        return view('fleet::fleet.tco_optimization', compact('contracts'));
    }

    public function originatePsak73(
        Request $request,
        OriginatePsak73LeaseWithRouAndPvLiabilityAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'contract_number' => 'required|string|max:50',
            'party_id' => 'required|integer',
            'duration_months' => 'required|integer|min:1',
            'monthly_rental_idr' => 'required|integer|min:0',
            'annual_ibr_bps' => 'nullable|integer|min:0',
            'security_deposit_idr' => 'nullable|integer|min:0',
        ]);

        $result = $action->handle(
            $validated['contract_number'],
            (int) $validated['party_id'],
            (int) $validated['duration_months'],
            (int) $validated['monthly_rental_idr'],
            (int) ($validated['annual_ibr_bps'] ?? 840),
            (int) ($validated['security_deposit_idr'] ?? 0)
        );

        return redirect()->route('fleet.lifecycle.psak73-leases')
            ->with('success', "PSAK 73 Lease {$result['contract']->contract_number} originated with PV Liability IDR ".number_format($result['pv_liability_idr']));
    }

    public function recognizeAmortization(
        Request $request,
        RecognizeMonthlyLeaseInterestAndRouAmortizationAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'contract_id' => 'required|integer',
            'annual_ibr_bps' => 'nullable|integer|min:0',
        ]);

        $result = $action->handle((int) $validated['contract_id'], (int) ($validated['annual_ibr_bps'] ?? 840));

        return redirect()->route('fleet.lifecycle.amortization-schedule')
            ->with('success', 'Recognized monthly interest IDR '.number_format($result['interest_idr']).' and principal IDR '.number_format($result['principal_idr']));
    }

    public function billExcess(
        Request $request,
        BillExcessMileageAndWearTearAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'unit_id' => 'required|integer',
            'actual_odometer' => 'required|integer|min:0',
            'allowed_km_cap' => 'required|integer|min:0',
            'rate_per_km' => 'nullable|integer|min:0',
            'wear_tear_idr' => 'nullable|integer|min:0',
            'condition_notes' => 'nullable|string|max:255',
        ]);

        $result = $action->handle(
            (int) $validated['unit_id'],
            (int) $validated['actual_odometer'],
            (int) $validated['allowed_km_cap'],
            (int) ($validated['rate_per_km'] ?? 2500),
            (int) ($validated['wear_tear_idr'] ?? 0),
            $validated['condition_notes'] ?? null
        );

        return redirect()->route('fleet.lifecycle.excess-mileage')
            ->with('success', 'Billed excess mileage IDR '.number_format($result['excess_mileage_fee_idr']).' and total IDR '.number_format($result['total_billed_idr']));
    }

    public function runTco(
        Request $request,
        OptimizeFleetTcoReplacementScheduleAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'contract_id' => 'required|integer',
        ]);

        $result = $action->handle((int) $validated['contract_id']);

        return redirect()->route('fleet.lifecycle.tco-optimization')
            ->with('success', "TCO optimization complete: {$result['recommendation']} with potential savings IDR ".number_format($result['projected_savings_idr']));
    }
}
