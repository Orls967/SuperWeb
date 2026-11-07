<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Application\Actions\DispatchHaulTruckAction;
use Modules\Mining\Http\Policies\MiningFase93AccessPolicy;
use Modules\Mining\Http\ViewModels\MiningFase93OperationsViewModel;

class MiningDispatchController extends Controller
{
    public function index(
        Request $request,
        MiningFase93OperationsViewModel $viewModel,
        MiningFase93AccessPolicy $policy,
        ClockInterface $clock
    ): View {
        $policy->authorizeDispatchAccess($request->user());

        $shiftDate = (string) $request->query('shift_date', $clock->now()->toDateString());
        $shiftCode = (string) $request->query('shift_code', 'SHIFT_A');

        $simParams = [
            'sim_trucks' => max(0, (int) $request->query('sim_trucks', 12)),
            'sim_shovels' => max(1, (int) $request->query('sim_shovels', 3)),
            'sim_loading' => max(1, (int) $request->query('sim_loading', 4)),
            'sim_cycle' => max(1, (int) $request->query('sim_cycle', 36)),
        ];

        return view('mining::dispatch.index', $viewModel->buildDispatchViewData($shiftDate, $shiftCode, $simParams));
    }

    public function assign(
        Request $request,
        DispatchHaulTruckAction $action,
        MiningFase93AccessPolicy $policy
    ): RedirectResponse {
        $policy->authorizeDispatchAccess($request->user());

        $validated = $request->validate([
            'site_id' => 'required|string',
            'pit_id' => 'required|string',
            'truck_id' => 'required|string',
            'shift_date' => 'required|date',
            'shift_code' => 'required|string',
            'target_payload_kg' => 'required|integer|min:1000',
        ]);

        $cycle = $action->execute(array_merge($validated, [
            'dispatcher_id' => $request->user()?->id,
            'is_override' => false,
        ]));

        return redirect()->route('mining.dispatch.ui')
            ->with('success', "Truk berhasil ditugaskan ke siklus {$cycle->cycle_seq} pada Shovel.");
    }

    public function override(
        Request $request,
        DispatchHaulTruckAction $action,
        MiningFase93AccessPolicy $policy
    ): RedirectResponse {
        $policy->authorizeDispatchAccess($request->user());

        $validated = $request->validate([
            'site_id' => 'required|string',
            'pit_id' => 'required|string',
            'truck_id' => 'required|string',
            'chosen_shovel_id' => 'required|string',
            'shift_date' => 'required|date',
            'shift_code' => 'required|string',
            'target_payload_kg' => 'required|integer|min:1000',
            'override_reason' => 'required|string|min:5',
        ]);

        $cycle = $action->execute(array_merge($validated, [
            'dispatcher_id' => $request->user()?->id,
            'is_override' => true,
        ]));

        return redirect()->route('mining.dispatch.ui')
            ->with('success', "Dispatcher override berhasil dicatat: {$cycle->override_reason}");
    }
}
