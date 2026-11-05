<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Insurance\Application\Actions\SwitchUnitLinkFundAction;
use Modules\Insurance\Application\Actions\ValuateUnitLinkNavAction;
use Modules\Insurance\Application\Services\LifeHealthReportService;
use Modules\Insurance\Domain\Models\UnitLinkFund;

final class UnitLinkController extends Controller
{
    public function portfolio(): View
    {
        $funds = UnitLinkFund::with('navHistories', 'holdings')->where('is_active', true)->get();

        return view('insurance::unitlink.portfolio', compact('funds'));
    }

    public function analytics(LifeHealthReportService $reportService): View
    {
        $report = $reportService->generateUnitLinkConsolidatedReport();

        return view('insurance::unitlink.analytics', compact('report'));
    }

    public function valuate(Request $request, ValuateUnitLinkNavAction $action): JsonResponse
    {
        $validated = $request->validate([
            'fund_code' => 'required|string|exists:ins_lh_unit_link_funds,fund_code',
            'valuation_date' => 'required|date',
            'nav_per_unit_idr' => 'required|integer|min:100',
        ]);

        $res = $action->execute(
            fundCode: $validated['fund_code'],
            valuationDate: $validated['valuation_date'],
            newNavPerUnitIdr: (int) $validated['nav_per_unit_idr'],
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Valuasi NAV harian unit link berhasil diperbarui.',
            'data' => $res,
        ]);
    }

    public function switch(Request $request, SwitchUnitLinkFundAction $action): JsonResponse
    {
        $validated = $request->validate([
            'policy_number' => 'required|string|exists:ins_lh_life_policies,policy_number',
            'source_fund_code' => 'required|string|exists:ins_lh_unit_link_funds,fund_code',
            'target_fund_code' => 'required|string|exists:ins_lh_unit_link_funds,fund_code',
            'units_to_switch' => 'required|integer|min:1',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? ('switch:'.$validated['policy_number'].':'.$validated['source_fund_code'].':'.$validated['target_fund_code']);

        $res = $action->execute(
            policyNumber: $validated['policy_number'],
            sourceFundCode: $validated['source_fund_code'],
            targetFundCode: $validated['target_fund_code'],
            unitsToSwitch: (int) $validated['units_to_switch'],
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengalihan unit investasi (fund switching) berhasil diproses.',
            'data' => $res,
        ]);
    }
}
