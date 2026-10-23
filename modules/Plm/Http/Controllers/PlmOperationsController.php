<?php

declare(strict_types=1);

namespace Modules\Plm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Plm\Application\Actions\AdvancePlmStageGateWithStrictGuardAction;
use Modules\Plm\Application\Actions\ConvertEbomToManufacturingMbomAction;
use Modules\Plm\Application\Actions\ExecuteCryptographicEcoChangeOrderAction;
use Modules\Plm\Application\Actions\RecordEncryptedLabStabilityTestAction;
use Modules\Plm\Domain\Models\EngineeringBom;
use Modules\Plm\Domain\Models\PlmProject;

class PlmOperationsController extends Controller
{
    public function index(): mixed
    {
        $projects = PlmProject::latest()->take(10)->get();
        $eboms = EngineeringBom::with('project')->latest()->take(10)->get();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'projects' => $projects,
                'eboms' => $eboms,
            ]);
        }

        return view('plm::plm_cockpit', compact('projects', 'eboms'));
    }

    public function advanceStage(
        Request $request,
        PlmProject $project,
        AdvancePlmStageGateWithStrictGuardAction $action
    ): JsonResponse {
        $data = $request->validate([
            'target_stage' => 'required|string',
            'gate_approval_signoff' => 'nullable|boolean',
        ]);

        $result = $action->execute($project, $data['target_stage'], [
            'gate_approval_signoff' => (bool) ($data['gate_approval_signoff'] ?? true),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Project advanced to Stage-Gate {$data['target_stage']}.",
            'data' => $result,
        ]);
    }

    public function convertMbom(
        Request $request,
        EngineeringBom $ebom,
        ConvertEbomToManufacturingMbomAction $action
    ): JsonResponse {
        $data = $request->validate([
            'routing_code' => 'nullable|string|max:40',
            'scrap_allowance_percent' => 'nullable|numeric|min:0',
        ]);

        $result = $action->execute($ebom, $data);

        return response()->json([
            'success' => true,
            'message' => 'Engineering EBOM released to Manufacturing MBOM.',
            'data' => $result,
        ], 201);
    }

    public function executeEco(
        Request $request,
        EngineeringBom $ebom,
        ExecuteCryptographicEcoChangeOrderAction $action
    ): JsonResponse {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'reason' => 'required|string',
            'cost_impact_idr' => 'required|integer',
            'disposition' => 'nullable|string|in:scrap,rework,run_out',
            'authorizer_signature' => 'nullable|string',
        ]);

        $result = $action->execute(
            $ebom,
            $data['title'],
            $data['reason'],
            (int) $data['cost_impact_idr'],
            $data['disposition'] ?? 'scrap',
            $data['authorizer_signature'] ?? 'CHIEF-ENG-SIG'
        );

        return response()->json([
            'success' => true,
            'message' => 'Cryptographic Engineering Change Order (ECO) executed.',
            'data' => $result,
        ], 201);
    }

    public function recordLabTest(
        Request $request,
        PlmProject $project,
        RecordEncryptedLabStabilityTestAction $action
    ): JsonResponse {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'formula_payload' => 'required|string',
            'stability_result' => 'nullable|string|in:pass,fail,pending',
            'sensory_score' => 'nullable|numeric|between:1.0,9.0',
        ]);

        $result = $action->execute(
            $project,
            $data['title'],
            $data['formula_payload'],
            $data['stability_result'] ?? 'pass',
            (float) ($data['sensory_score'] ?? 8.5)
        );

        return response()->json([
            'success' => true,
            'message' => 'Encrypted lab notebook stability test recorded.',
            'data' => $result,
        ], 201);
    }
}
