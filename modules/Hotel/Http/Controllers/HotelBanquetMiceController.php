<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\ProcessBanquetEventOrderApprovalAction;
use Modules\Hotel\Domain\Models\MiceContract;

/**
 * Controller: Pemesanan Perjamuan (Banquet) & Koordinasi MICE.
 */
final class HotelBanquetMiceController extends Controller
{
    public function index(): View
    {
        $contracts = MiceContract::latest()->take(25)->get();

        return view('hotel::banquet_event_order_board', [
            'contracts' => $contracts,
        ]);
    }

    public function approve(
        Request $request,
        string $contractId,
        ProcessBanquetEventOrderApprovalAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'contract_value_idr' => 'required|integer|min:1',
            'kitchen_bom_items' => 'required|array',
            'av_equipment_cost_idr' => 'required|integer|min:0',
            'decor_florist_cost_idr' => 'required|integer|min:0',
        ]);

        $res = $action->execute(
            miceContractId: $contractId,
            contractValueIdr: (int) $validated['contract_value_idr'],
            kitchenBomItems: (array) $validated['kitchen_bom_items'],
            avEquipmentCostIdr: (int) $validated['av_equipment_cost_idr'],
            decorFloristCostIdr: (int) $validated['decor_florist_cost_idr']
        );

        return response()->json([
            'status' => $res['is_approved'] ? 'success' : 'rejected',
            'data' => $res,
        ], $res['is_approved'] ? 200 : 422);
    }
}
