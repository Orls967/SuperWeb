<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\ProcessWeddingMilestonePaymentAction;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelWeddingContractController extends Controller
{
    public function index(): View
    {
        $contracts = MiceContract::with('weddingMilestones')->latest()->get();

        return view('hotel::wedding_milestone_contract_hub', [
            'contracts' => $contracts,
        ]);
    }

    public function giftRegistry(): View
    {
        return view('hotel::wedding_guest_gift_registry_view');
    }

    public function processMilestonePayment(Request $request, ProcessWeddingMilestonePaymentAction $action): JsonResponse
    {
        $validated = $request->validate([
            'contract_id' => 'required|exists:htl_mice_contracts,id',
            'milestone_index' => 'required|integer|min:1|max:4',
            'amount_paid_idr' => 'required|integer|min:1',
            'payment_reference' => 'required|string|max:64',
        ]);

        $contract = MiceContract::findOrFail($validated['contract_id']);

        $milestone = $action->execute(
            contract: $contract,
            milestoneIndex: (int) $validated['milestone_index'],
            amountPaidIdr: (int) $validated['amount_paid_idr'],
            paymentReference: $validated['payment_reference']
        );

        return response()->json([
            'success' => true,
            'milestone_index' => $milestone->milestone_index,
            'status' => $milestone->status,
            'paid_amount_idr' => $milestone->paid_amount_idr,
            'payment_reference' => $milestone->payment_reference,
        ]);
    }
}
