<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mining\Application\Actions\ExecuteSmelterMassBalanceRecoveryAction;
use Modules\Mining\Application\Actions\ReconcileOreGradeMineToPortVarianceAction;
use Modules\Mining\Application\Actions\RecordWeighbridgeHashChainTicketAction;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\SmelterRun;
use Modules\Mining\Domain\Models\WeighbridgeTicket;

class MiningWeighbridgeSmelterController extends Controller
{
    public function weighbridgeTickets(Request $request): View
    {
        $sites = MiningSite::all();
        $tickets = WeighbridgeTicket::latest()->paginate(15);

        return view('mining::mining.weighbridge_tickets', compact('sites', 'tickets'));
    }

    public function recordTicket(
        Request $request,
        RecordWeighbridgeHashChainTicketAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'site_id' => 'required|string',
            'truck_plate_number' => 'required|string|max:16',
            'gross_weight_ton' => 'required|numeric|min:1',
            'tare_weight_ton' => 'required|numeric|min:0',
            'nickel_grade_percentage' => 'required|numeric|min:0',
            'destination_stockpile' => 'required|string|max:64',
        ]);

        $result = $action->handle(
            $validated['site_id'],
            $validated['truck_plate_number'],
            (float) $validated['gross_weight_ton'],
            (float) $validated['tare_weight_ton'],
            (float) $validated['nickel_grade_percentage'],
            $validated['destination_stockpile']
        );

        return redirect()->route('mining.operations.weighbridge')
            ->with('success', "Weighbridge Ticket {$result['ticket_number']} recorded! Net: {$result['net_weight_ton']} Ton (Hash: ".substr($result['hash'], 0, 16).'...).');
    }

    public function oreGradeReconciliation(Request $request): View
    {
        return view('mining::mining.ore_grade_reconciliation');
    }

    public function reconcileAssay(
        Request $request,
        ReconcileOreGradeMineToPortVarianceAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'lot_code' => 'required|string|max:32',
            'mine_grade_ni_pct' => 'required|numeric|min:0',
            'port_grade_ni_pct' => 'required|numeric|min:0',
            'lot_tonnage' => 'required|numeric|min:1',
        ]);

        $result = $action->handle(
            $validated['lot_code'],
            (float) $validated['mine_grade_ni_pct'],
            (float) $validated['port_grade_ni_pct'],
            (float) $validated['lot_tonnage']
        );

        $msg = "Mine-to-Port Assay Reconciled: Variance {$result['grade_variance_pct']}% Ni &ndash; Status: {$result['status']}.";

        return redirect()->route('mining.operations.grade-reconciliation')
            ->with($result['is_dispute_flagged'] ? 'error' : 'success', $msg);
    }

    public function smelterRuns(Request $request): View
    {
        $sites = MiningSite::all();
        $runs = SmelterRun::latest()->paginate(15);

        return view('mining::mining.smelter_recovery', compact('sites', 'runs'));
    }

    public function executeSmelter(
        Request $request,
        ExecuteSmelterMassBalanceRecoveryAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'site_id' => 'required|string',
            'run_code' => 'required|string|max:32',
            'output_commodity' => 'required|string|max:32',
            'input_ore_tonnage' => 'required|numeric|min:1',
            'input_grade_pct' => 'required|numeric|min:0',
            'output_metal_tonnage' => 'required|numeric|min:0',
            'byproduct_slag_tonnage' => 'required|numeric|min:0',
            'byproduct_revenue_idr' => 'nullable|integer|min:0',
        ]);

        $result = $action->handle(
            $validated['site_id'],
            $validated['run_code'],
            $validated['output_commodity'],
            (float) $validated['input_ore_tonnage'],
            (float) $validated['input_grade_pct'],
            (float) $validated['output_metal_tonnage'],
            (float) $validated['byproduct_slag_tonnage'],
            (int) ($validated['byproduct_revenue_idr'] ?? 0)
        );

        return redirect()->route('mining.operations.smelter')
            ->with('success', "Smelter Run {$result['smelter_run']->run_code} Completed: Metal Recovery {$result['recovery_rate_pct']}% (Slag Revenue: IDR ".number_format($result['byproduct_revenue_idr']).').');
    }
}
