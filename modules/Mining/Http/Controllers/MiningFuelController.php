<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Mining\Application\Actions\DispenseFuelToEquipmentAction;
use Modules\Mining\Application\Sagas\FuelAnomalyHoldSaga;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Http\Policies\MiningFase93AccessPolicy;
use Modules\Mining\Http\ViewModels\MiningFase93OperationsViewModel;

class MiningFuelController extends Controller
{
    public function index(
        Request $request,
        MiningFase93OperationsViewModel $viewModel,
        MiningFase93AccessPolicy $policy
    ): View {
        $policy->authorizeFuelAccess($request->user());

        $statusFilter = $request->query('status') ? (string) $request->query('status') : null;
        $contractor = $request->query('contractor') ? (string) $request->query('contractor') : null;
        $from = (string) $request->query('from', '1970-01-01');
        $to = (string) $request->query('to', '2999-12-31');

        return view('mining::fuel.index', $viewModel->buildFuelViewData($statusFilter, $contractor, $from, $to));
    }

    public function resolve(
        Request $request,
        MiningFuelAnomalyCase $case,
        FuelAnomalyHoldSaga $saga,
        MiningFase93AccessPolicy $policy
    ): RedirectResponse {
        $policy->authorizeFuelAccess($request->user());

        $validated = $request->validate([
            'action' => 'required|in:deduct,release',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $validated['action'] === 'deduct'
                ? $saga->resolveDeducted($case, $request->user(), $validated['notes'] ?? null)
                : $saga->resolveReleased($case, $request->user(), $validated['notes'] ?? null);
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()->route('mining.fuel')->with('error', $e->getMessage());
        }

        return redirect()->route('mining.fuel')->with('success', "Kasus {$case->case_number} diselesaikan ({$validated['action']}).");
    }

    public function dispense(
        Request $request,
        DispenseFuelToEquipmentAction $action,
        MiningFase93AccessPolicy $policy
    ): RedirectResponse {
        $policy->authorizeFuelAccess($request->user());

        $v = $request->validate([
            'storage_id' => 'required|string',
            'equipment_id' => 'required|string',
            'volume_liters' => 'required|integer|min:1',
            'unit_price_idr_per_liter' => 'required|integer|min:1',
            'flowmeter_start' => 'required|string|max:32',
            'flowmeter_end' => 'required|string|max:32',
        ]);

        try {
            $record = $action->execute([
                'storage_id' => $v['storage_id'],
                'equipment_id' => $v['equipment_id'],
                'volume_ml' => $v['volume_liters'] * 1000,
                'unit_price_idr_per_liter' => $v['unit_price_idr_per_liter'],
                'flowmeter_start' => $v['flowmeter_start'],
                'flowmeter_end' => $v['flowmeter_end'],
                'operator_ref' => (string) $request->user()->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('mining.fuel')->with('error', $e->getMessage());
        }

        return redirect()->route('mining.fuel')->with('success', "Pengisian {$record->dispense_code} tercatat.");
    }
}
