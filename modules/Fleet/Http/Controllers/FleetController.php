<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Fleet\Application\Services\FleetLeasingService;
use Modules\Fleet\Domain\Models\FleetContract;
use Modules\Fleet\Domain\Models\FleetContractUnit;

class FleetController extends Controller
{
    public function index(Request $request): View
    {
        $contracts = FleetContract::with('units')->latest()->take(10)->get();
        $units = FleetContractUnit::with('contract')->latest()->take(10)->get();

        return view('fleet::fleet.index', compact('contracts', 'units'));
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

        return redirect()->route('fleet.index')->with('success', "Lease month {$validated['month_index']} amortized for contract {$contract->contract_number}.");
    }
}
