<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mining\Application\Services\ContractorFuelReconciliationService;
use Modules\Mining\Domain\Enums\FuelAnomalyCaseStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningContractorPaymentHold;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\StateMachines\FuelAnomalyCaseStateMachine;
use Modules\Mining\Http\Policies\MiningFase93AccessPolicy;

class MiningContractorPortalController extends Controller
{
    public function index(
        Request $request,
        ContractorFuelReconciliationService $reconciliation,
        MiningFase93AccessPolicy $policy
    ): View {
        $user = $request->user();
        $partyId = (string) $user->id;

        $myCases = MiningFuelAnomalyCase::where('contractor_party_ref', $partyId)
            ->with('equipment')
            ->orderByDesc('shift_date')
            ->orderBy('case_number')
            ->paginate(15);

        $myHolds = MiningContractorPaymentHold::where('contractor_party_ref', $partyId)
            ->orderBy('hold_reference')
            ->get()
            ->filter(fn (MiningContractorPaymentHold $h) => $policy->canViewContractorHold($user, $h))
            ->values();

        $summary = $reconciliation->summarize($partyId, (string) $request->query('from', '1970-01-01'), (string) $request->query('to', '2999-12-31'));

        return view('mining::contractor.index', compact('myCases', 'myHolds', 'summary'));
    }

    public function dispute(
        Request $request,
        FuelAnomalyCaseStateMachine $machine,
        MiningFase93AccessPolicy $policy
    ): JsonResponse|RedirectResponse {
        $user = $request->user();
        $validated = $request->validate([
            'case_id' => 'required|string',
            'reason' => 'required|string|min:5',
        ]);

        $case = MiningFuelAnomalyCase::findOrFail($validated['case_id']);

        $policy->assertContractorOwnsFuelCase($user, $case);

        try {
            $machine->transition($case, FuelAnomalyCaseStatus::DISPUTED, (string) $user->id, $validated['reason']);
        } catch (InvalidMiningStateTransitionException $e) {
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $e->getMessage()], 422)
                : redirect()->route('mining.contractor.portal')->with('error', $e->getMessage());
        }

        $case->update(['dispute_reason' => $validated['reason']]);

        return $request->wantsJson()
            ? response()->json(['success' => true, 'case_number' => $case->case_number, 'status' => 'disputed'])
            : redirect()->route('mining.contractor.portal')->with('success', 'Sanggahan diajukan untuk penelaahan.');
    }
}
