<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\ConvertProposalToMiceContractAction;
use Modules\Hotel\Application\Actions\CreateMiceProposalWithMarginGuardAction;
use Modules\Hotel\Domain\Models\HtlEventProposal;
use Modules\Hotel\Domain\Models\HtlMiceLead;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelMicePipelineController extends Controller
{
    public function index(): View
    {
        $leads = HtlMiceLead::latest()->take(10)->get();
        $proposals = HtlEventProposal::with('lead')->latest()->take(10)->get();
        $contracts = MiceContract::latest()->take(10)->get();

        $leadsCount = HtlMiceLead::count();
        $totalPipelineValue = (int) HtlEventProposal::whereIn('status', ['DRAFT', 'APPROVED_OFFER', 'OFFERED'])->sum('total_revenue_idr');
        $avgGrossMargin = (float) (HtlEventProposal::avg('gross_margin_percent') ?: 45.0);
        $confirmedContractsCount = MiceContract::where('status', 'CONFIRMED')->count();

        return view('hotel::mice_sales_pipeline_board', [
            'leads' => $leads,
            'proposals' => $proposals,
            'contracts' => $contracts,
            'leadsCount' => $leadsCount,
            'totalPipelineValue' => $totalPipelineValue,
            'avgGrossMargin' => $avgGrossMargin,
            'confirmedContractsCount' => $confirmedContractsCount,
        ]);
    }

    public function createProposal(Request $request, CreateMiceProposalWithMarginGuardAction $action): JsonResponse
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:htl_mice_leads,id',
            'title' => 'required|string|max:255',
            'revenue' => 'required|array',
            'costs' => 'required|array',
            'validity_days' => 'nullable|integer|min:1|max:90',
        ]);

        $lead = HtlMiceLead::findOrFail($validated['lead_id']);

        $result = $action->execute(
            lead: $lead,
            title: $validated['title'],
            revenueBreakdown: array_map('intval', $validated['revenue']),
            costBreakdown: array_map('intval', $validated['costs']),
            validityDays: (int) ($validated['validity_days'] ?? 14),
            operatorReference: (string) ($request->user()?->id ?? 'USR-SALES-01')
        );

        return response()->json([
            'success' => true,
            'proposal_code' => $result['proposal']->proposal_code,
            'status' => $result['proposal']->status,
            'gross_margin_percent' => $result['certificate']->grossMarginPercent,
            'is_compliant' => $result['certificate']->isCompliant,
            'signature_hash' => $result['certificate']->signatureHash,
        ]);
    }

    public function convertToContract(Request $request, ConvertProposalToMiceContractAction $action): JsonResponse
    {
        $validated = $request->validate([
            'proposal_id' => 'required|exists:htl_event_proposals,id',
            'event_start_date' => 'required|date|after:today',
            'event_end_date' => 'required|date|after_or_equal:event_start_date',
            'signing_date' => 'required|date',
            'room_block_initial_rooms' => 'nullable|integer|min:0',
            'property_id' => 'nullable|string|max:36',
        ]);

        $proposal = HtlEventProposal::findOrFail($validated['proposal_id']);

        $contract = $action->execute(
            proposal: $proposal,
            eventStartDate: $validated['event_start_date'],
            eventEndDate: $validated['event_end_date'],
            signingDate: $validated['signing_date'],
            roomBlockInitialRooms: isset($validated['room_block_initial_rooms']) ? (int) $validated['room_block_initial_rooms'] : null,
            propertyId: $validated['property_id'] ?? null,
            operatorReference: (string) ($request->user()?->id ?? 'USR-SALES-01')
        );

        return response()->json([
            'success' => true,
            'contract_code' => $contract->event_code,
            'total_contract_amount_idr' => $contract->contract_total_value_idr,
            'is_bridal_suite_complimentary' => $contract->is_bridal_suite_complimentary,
        ]);
    }
}
