<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Wms\Application\Actions\DispatchRobotMissionAction;
use Modules\Wms\Application\Actions\EnforceZoneSafetyInterlockAction;
use Modules\Wms\Application\Actions\TriggerRobotFallbackAction;
use Modules\Wms\Application\Services\RoboticsFleetOrchestratorService;
use Modules\Wms\Http\Requests\DispatchRobotMissionRequest;
use Modules\Wms\Http\Requests\EnforceZoneInterlockRequest;
use Modules\Wms\Http\ViewModels\RoboticsMissionBoardViewModel;
use Modules\Wms\Http\ViewModels\RoboticsRoiPortfolioViewModel;
use Modules\Wms\Models\RoboticsPlan;
use Modules\Wms\Models\RoboticsRoiProposal;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotUnit;
use Modules\Wms\Models\RobotZoneLock;

/**
 * Controller operasional armada robotik gudang, misi, interlock keselamatan, & ROI (Fase 279).
 */
class WmsRoboticsController extends Controller
{
    public function fleet(RoboticsFleetOrchestratorService $orchestrator): View
    {
        $firstWh = (string) (RobotUnit::query()->value('warehouse_id') ?? '');

        return view('wms::robotics.fleet', [
            'summary' => $orchestrator->fleetSummary(),
            'dispatchPreview' => $firstWh !== '' ? $orchestrator->previewInterlockAwareDispatch($firstWh) : ['assignments' => [], 'unassigned_mission_ids' => [], 'total_cost' => 0],
            'units' => RobotUnit::query()->orderBy('robot_code')->limit(50)->get(),
            'locks' => RobotZoneLock::query()->where('lock_status', 'active')->orderBy('zone_code')->limit(50)->get(),
        ]);
    }

    public function missions(RoboticsMissionBoardViewModel $boardVm): View
    {
        $missions = RobotMission::query()->latest('id')->limit(50)->get();

        return view('wms::robotics.missions', [
            'missions' => $missions,
            'board' => $boardVm->present($missions),
        ]);
    }

    public function roi(RoboticsRoiPortfolioViewModel $portfolioVm): View
    {
        $plans = RoboticsPlan::query()->latest('id')->limit(30)->get();
        $proposals = RoboticsRoiProposal::query()->latest('id')->limit(30)->get();

        return view('wms::robotics.roi', [
            'plans' => $plans,
            'proposals' => $proposals,
            'portfolio' => $portfolioVm->present($proposals),
        ]);
    }

    public function dispatchMission(
        DispatchRobotMissionRequest $request,
        DispatchRobotMissionAction $action,
    ): RedirectResponse {
        $data = $request->validated();
        $mission = $action->execute(
            warehouseId: (string) $data['warehouse_id'],
            robotUnitId: (int) $data['robot_unit_id'],
            zoneCode: (string) $data['zone_code'],
            missionCode: (string) $data['mission_code'],
            waveId: isset($data['wave_id']) ? (int) $data['wave_id'] : null,
            operatorUserId: $request->user()?->id,
        );

        return redirect()->route('wms.robotics.missions')->with('status', "Misi {$mission->mission_code} aktif.");
    }

    public function triggerFallback(
        Request $request,
        int $mission,
        TriggerRobotFallbackAction $action,
    ): RedirectResponse {
        $operatorId = (int) ($request->user()?->id ?? 1);
        $updated = $action->execute(
            missionId: $mission,
            operatorUserId: $operatorId,
            reason: (string) $request->input('reason', 'operator_manual_takeover'),
        );

        return redirect()->route('wms.robotics.missions')->with('status', "Misi {$updated->mission_code} dialihkan ke fallback manual.");
    }

    public function enforceInterlock(
        EnforceZoneInterlockRequest $request,
        EnforceZoneSafetyInterlockAction $action,
    ): RedirectResponse {
        $data = $request->validated();
        $lock = $action->execute(
            warehouseId: (string) $data['warehouse_id'],
            zoneCode: (string) $data['zone_code'],
            humanPresent: (bool) $data['human_present'],
        );

        return redirect()->route('wms.robotics.fleet')->with('status', "Interlock zona {$lock->zone_code} diperbarui (limit {$lock->speed_limit_mm_s} mm/s).");
    }
}
