<?php

declare(strict_types=1);

namespace Modules\Esg\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Esg\Application\Actions\CalculateScope123GhgEmissionAction;
use Modules\Esg\Application\Actions\EvaluateSupplierGriEsgScorecardAction;
use Modules\Esg\Application\Actions\PurchaseAndInventoryCarbonCreditLedgerAction;
use Modules\Esg\Application\Actions\RetireCarbonCreditForNetZeroOffsetAction;
use Modules\Esg\Domain\Models\CarbonCredit;
use Modules\Esg\Domain\Models\EsgEmission;
use Modules\Esg\Domain\Models\OffsetRetirement;
use Modules\Esg\Domain\Models\SupplierScore;

class EsgOperationsController extends Controller
{
    public function index(): mixed
    {
        $emissions = EsgEmission::latest()->take(10)->get();
        $credits = CarbonCredit::where('status', 'active')->latest()->take(10)->get();
        $retirements = OffsetRetirement::with('credit')->latest()->take(10)->get();
        $scores = SupplierScore::latest()->take(10)->get();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'emissions' => $emissions,
                'carbon_credits' => $credits,
                'retirements' => $retirements,
                'supplier_scores' => $scores,
            ]);
        }

        return view('esg::esg_cockpit', compact('emissions', 'credits', 'retirements', 'scores'));
    }

    public function calculateEmission(
        Request $request,
        CalculateScope123GhgEmissionAction $action
    ): JsonResponse {
        $data = $request->validate([
            'entity_id' => 'required|string',
            'scope' => 'required|string|in:scope_1,scope_2,scope_3',
            'activity_type' => 'required|string',
            'activity_data_amount' => 'required|numeric|min:0.0001',
            'activity_uom' => 'required|string',
            'emission_factor' => 'required|numeric|min:0.000001',
            'reporting_period' => 'required|date',
            'source_module' => 'nullable|string',
            'source_reference' => 'nullable|string',
        ]);

        $result = $action->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'GHG emission calculated and recorded.',
            'data' => $result,
        ], 201);
    }

    public function purchaseCredit(
        Request $request,
        PurchaseAndInventoryCarbonCreditLedgerAction $action
    ): JsonResponse {
        $data = $request->validate([
            'certificate_number' => 'required|string|unique:esg_carbon_credits,certificate_number',
            'registry' => 'required|string|in:VERRA,GOLD_STANDARD,IDX_CARBON',
            'project_name' => 'required|string',
            'project_type' => 'required|string',
            'vintage_year' => 'required|integer',
            'quantity_co2e_tons' => 'required|numeric|min:0.01',
            'cost_per_ton_idr' => 'required|integer|min:1',
        ]);

        $result = $action->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'Carbon credits purchased and posted to inventory ledger.',
            'data' => $result,
        ], 201);
    }

    public function retireCredit(
        Request $request,
        CarbonCredit $credit,
        RetireCarbonCreditForNetZeroOffsetAction $action
    ): JsonResponse {
        $data = $request->validate([
            'entity_id' => 'required|string',
            'retired_quantity_tons' => 'required|numeric|min:0.01',
            'reason' => 'required|string',
            'certificate_url' => 'nullable|url',
        ]);

        $result = $action->execute(
            $credit,
            $data['entity_id'],
            (float) $data['retired_quantity_tons'],
            $data['reason'],
            $data['certificate_url'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Carbon credits retired and posted to offset ledger.',
            'data' => $result,
        ], 201);
    }

    public function evaluateSupplier(
        Request $request,
        EvaluateSupplierGriEsgScorecardAction $action
    ): JsonResponse {
        $data = $request->validate([
            'supplier_id' => 'required|string',
            'evaluation_year' => 'required|string|size:4',
            'environmental_score' => 'required|integer|between:0,100',
            'social_score' => 'required|integer|between:0,100',
            'governance_score' => 'required|integer|between:0,100',
            'certification_list' => 'nullable|string',
            'audit_notes' => 'nullable|string',
        ]);

        $score = $action->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'Supplier GRI ESG scorecard evaluated.',
            'supplier_score' => $score,
        ], 201);
    }
}
