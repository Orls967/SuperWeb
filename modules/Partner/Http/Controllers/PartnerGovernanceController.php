<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Partner\Application\Actions\AuditIpTerritoryLicensingAction;
use Modules\Partner\Application\Actions\CalculateJvEquityProfitShareAction;
use Modules\Partner\Application\Actions\SettlePartnerMilestoneCoDevAction;
use Modules\Partner\Domain\Models\JointPlan;
use Modules\Partner\Domain\Models\Partner;

class PartnerGovernanceController extends Controller
{
    public function calculateProfitShare(
        Request $request,
        Partner $partner,
        CalculateJvEquityProfitShareAction $action
    ): JsonResponse {
        $data = $request->validate([
            'period' => 'required|string',
            'gross_revenue_idr' => 'required|integer|min:0',
            'deductible_cost_idr' => 'required|integer|min:0',
            'share_rate_percent' => 'required|numeric|min:0.1|max:100',
        ]);

        $res = $action->execute(
            $partner,
            $data['period'],
            (int) $data['gross_revenue_idr'],
            (int) $data['deductible_cost_idr'],
            (float) $data['share_rate_percent']
        );

        return response()->json($res);
    }

    public function settleMilestone(
        Request $request,
        JointPlan $jointPlan,
        SettlePartnerMilestoneCoDevAction $action
    ): JsonResponse {
        $data = $request->validate([
            'milestone_name' => 'required|string',
            'payout_idr' => 'required|integer|min:1',
            'kpis' => 'nullable|array',
        ]);

        $res = $action->execute(
            $jointPlan,
            $data['milestone_name'],
            (int) $data['payout_idr'],
            $data['kpis'] ?? []
        );

        return response()->json($res);
    }

    public function auditIp(
        Partner $partner,
        AuditIpTerritoryLicensingAction $action
    ): JsonResponse {
        $res = $action->execute($partner);

        return response()->json($res);
    }
}
