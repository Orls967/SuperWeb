<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Insurance\Application\Actions\DistributeTakafulSurplusAction;
use Modules\Insurance\Application\Actions\IssueTakafulPolicyAction;
use Modules\Insurance\Application\Services\ShariaSupervisoryBoardReportService;
use Modules\Insurance\Domain\Models\TakafulFund;
use Modules\Insurance\Domain\Models\TakafulParticipant;
use Modules\Insurance\Domain\Models\TakafulSurplusDistribution;

final class TakafulController extends Controller
{
    public function index(): View
    {
        $funds = TakafulFund::with('participants', 'surplusDistributions')->where('is_active', true)->get();
        $participants = TakafulParticipant::with('fund')->latest()->paginate(20);

        return view('insurance::takaful.index', compact('funds', 'participants'));
    }

    public function actuarialBoard(ShariaSupervisoryBoardReportService $service): View
    {
        $report = $service->generateDpsComplianceReport();

        return view('insurance::takaful.actuarial_board', compact('report'));
    }

    public function surplus(): View
    {
        $distributions = TakafulSurplusDistribution::with('fund')->latest()->paginate(20);

        return view('insurance::takaful.surplus', compact('distributions'));
    }

    public function store(Request $request, IssueTakafulPolicyAction $action): JsonResponse
    {
        $validated = $request->validate([
            'policy_number' => 'required|string|max:60|unique:ins_tk_participants,policy_number',
            'participant_party_id' => 'required|uuid',
            'fund_code' => 'required|string|exists:ins_tk_funds,fund_code',
            'plan_type' => 'required|string|in:FAMILY_TAKAFUL,GENERAL_TAKAFUL',
            'annual_contribution_idr' => 'required|integer|min:100000',
            'sum_covered_idr' => 'required|integer|min:1000000',
            'tabarru_portion_bps' => 'nullable|integer|min:1000|max:9000',
            'ujrah_portion_bps' => 'nullable|integer|min:1000|max:9000',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? ('tk:iss:'.$validated['policy_number']);

        $res = $action->execute(
            policyNumber: $validated['policy_number'],
            participantPartyId: $validated['participant_party_id'],
            fundCode: $validated['fund_code'],
            planType: $validated['plan_type'],
            annualContributionIdr: (int) $validated['annual_contribution_idr'],
            sumCoveredIdr: (int) $validated['sum_covered_idr'],
            tabarruPortionBps: (int) ($validated['tabarru_portion_bps'] ?? 7000),
            ujrahPortionBps: (int) ($validated['ujrah_portion_bps'] ?? 3000),
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Polis kepesertaan Takaful berhasil diterbitkan.',
            'participant' => $res,
        ], 201);
    }

    public function distribute(Request $request, DistributeTakafulSurplusAction $action): JsonResponse
    {
        $validated = $request->validate([
            'fund_code' => 'required|string|exists:ins_tk_funds,fund_code',
            'fiscal_year' => 'required|integer|min:2020|max:2030',
            'technical_reserve_addition_idr' => 'required|integer|min:0',
            'participant_share_bps' => 'nullable|integer|min:0|max:10000',
            'contingency_reserve_bps' => 'nullable|integer|min:0|max:10000',
            'operator_mudharabah_bps' => 'nullable|integer|min:0|max:10000',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? ('tk:surplus:'.$validated['fund_code'].':'.$validated['fiscal_year']);

        $res = $action->execute(
            fundCode: $validated['fund_code'],
            fiscalYear: (int) $validated['fiscal_year'],
            technicalReserveAdditionIdr: (int) $validated['technical_reserve_addition_idr'],
            participantShareBps: (int) ($validated['participant_share_bps'] ?? 5000),
            contingencyReserveBps: (int) ($validated['contingency_reserve_bps'] ?? 3000),
            operatorMudharabahBps: (int) ($validated['operator_mudharabah_bps'] ?? 2000),
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Surplus underwriting dana Tabarru berhasil dibagikan.',
            'distribution' => $res,
        ]);
    }
}
