<?php

declare(strict_types=1);

namespace Modules\Agri\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Agri\Application\Actions\GradeHarvestQualityAtCollectionCenterAction;
use Modules\Agri\Application\Actions\RegisterPlasmaFarmerContractAction;
use Modules\Agri\Application\Actions\SettleFarmerPayoutNetOfInputLoanViaLedgerAction;
use Modules\Agri\Application\Services\AgriService;
use Modules\Agri\Domain\Models\AgriCollectionBatch;
use Modules\Agri\Domain\Models\AgriContract;

class AgriOperationsController extends Controller
{
    public function dashboard(AgriService $service): View
    {
        $audit = $service->auditAgri();

        return view('agri::agri-operations-dashboard', compact('audit'));
    }

    public function contractsIndex(): View
    {
        $contracts = AgriContract::with('farmer')->latest()->take(30)->get();

        return view('agri::plasma-farmer-contracts', compact('contracts'));
    }

    public function registerContract(Request $request, RegisterPlasmaFarmerContractAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'farmer_id' => 'nullable|string',
            'full_name' => 'nullable|string',
            'farmer_group_name' => 'nullable|string',
            'commodity' => 'required|string',
            'land_area_hectares' => 'nullable|numeric|min:0.1',
            'target_yield_kg' => 'required|numeric|min:1',
            'guaranteed_floor_price_idr_per_kg' => 'required|integer|min:1',
            'seed_advance_value_idr' => 'nullable|integer|min:0',
            'fertilizer_advance_value_idr' => 'nullable|integer|min:0',
        ]);

        $contract = $action->execute($validated);

        return redirect()->route('agri.contracts.index')->with('success', "Contract {$contract->contract_number} successfully registered.");
    }

    public function gradingIndex(): View
    {
        return view('agri::harvest-quality-grading');
    }

    public function evaluateGrading(Request $request, GradeHarvestQualityAtCollectionCenterAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'contract_id' => 'required|string',
            'gross_weight_kg' => 'required|numeric|min:1',
            'moisture_percentage' => 'nullable|numeric|min:0',
            'defect_percentage' => 'nullable|numeric|min:0',
        ]);

        $result = $action->execute($validated);

        return redirect()->route('agri.grading.index')->with('success', "Grading evaluated: {$result['grade']} with multiplier {$result['price_multiplier']}. Gross value: Rp ".number_format($result['gross_payout_idr'], 0, ',', '.'));
    }

    public function payoutIndex(): View
    {
        $batches = AgriCollectionBatch::with('contract')->latest()->take(30)->get();

        return view('agri::farmer-payout-settlement', compact('batches'));
    }

    public function settlePayout(Request $request, SettleFarmerPayoutNetOfInputLoanViaLedgerAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'contract_id' => 'required|string',
            'collection_center_id' => 'required|string',
            'gross_weight_kg' => 'required|numeric|min:1',
            'grade' => 'nullable|string',
            'destination_unit' => 'nullable|string',
        ]);

        $batch = $action->execute($validated);

        return redirect()->route('agri.payout.index')->with('success', "Harvest batch {$batch->batch_number} settled. Net payout: Rp ".number_format($batch->net_payout_idr, 0, ',', '.'));
    }
}
