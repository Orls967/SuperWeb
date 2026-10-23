<?php

declare(strict_types=1);

namespace Modules\International\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\International\Application\Actions\ApplyTaxTreatyDtaReliefAction;
use Modules\International\Application\Actions\CalculateCrossBorderRoyaltyWithWhtAction;
use Modules\International\Application\Actions\ReconcileOemToolingAmortizationAction;
use Modules\International\Domain\Models\ForeignEntity;
use Modules\International\Domain\Models\OemContract;
use Modules\International\Domain\Models\TaxTreaty;
use Modules\International\Domain\Models\TechnologyLicense;

class InternationalTaxAndRoyaltyController extends Controller
{
    public function index(): mixed
    {
        $entities = ForeignEntity::latest()->take(10)->get();
        $licenses = TechnologyLicense::with('foreignEntity')->latest()->take(10)->get();
        $treaties = TaxTreaty::all();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'foreign_entities' => $entities,
                'licenses' => $licenses,
                'tax_treaties' => $treaties,
            ]);
        }

        return view('international::international_dashboard', compact('entities', 'licenses', 'treaties'));
    }

    public function calculateRoyalty(
        Request $request,
        TechnologyLicense $license,
        CalculateCrossBorderRoyaltyWithWhtAction $action
    ): JsonResponse {
        $data = $request->validate([
            'sales_revenue_idr' => 'required|integer|min:1',
            'has_valid_dgt_form' => 'nullable|boolean',
        ]);

        $result = $action->execute(
            $license,
            (int) $data['sales_revenue_idr'],
            (bool) ($data['has_valid_dgt_form'] ?? false)
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function applyTreatyRelief(
        Request $request,
        ApplyTaxTreatyDtaReliefAction $action
    ): JsonResponse {
        $data = $request->validate([
            'country_code' => 'required|string|size:2',
            'income_type' => 'required|string|in:royalty,interest,dividend,services',
            'gross_payment_idr' => 'required|integer|min:1',
            'has_valid_dgt1_form' => 'required|boolean',
        ]);

        $result = $action->execute(
            $data['country_code'],
            $data['income_type'],
            (int) $data['gross_payment_idr'],
            (bool) $data['has_valid_dgt1_form']
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function reconcileOemTooling(
        Request $request,
        OemContract $contract,
        ReconcileOemToolingAmortizationAction $action
    ): JsonResponse {
        $data = $request->validate([
            'units_produced' => 'required|integer|min:1',
            'tooling_total_cost_idr' => 'required|integer|min:0',
            'total_contract_expected_units' => 'required|integer|min:1',
        ]);

        $result = $action->execute(
            $contract,
            (int) $data['units_produced'],
            (int) $data['tooling_total_cost_idr'],
            (int) $data['total_contract_expected_units']
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
