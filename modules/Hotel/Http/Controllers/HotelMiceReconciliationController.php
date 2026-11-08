<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\AuditMiceEventReconciliationAction;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelMiceReconciliationController extends Controller
{
    public function index(): View
    {
        $contracts = MiceContract::latest()->get();

        return view('hotel::mice_master_folio_reconciliation_view', [
            'contracts' => $contracts,
        ]);
    }

    public function reconcile(Request $request, AuditMiceEventReconciliationAction $action): JsonResponse
    {
        $validated = $request->validate([
            'contract_id' => 'required|exists:htl_mice_contracts,id',
        ]);

        $contract = MiceContract::findOrFail($validated['contract_id']);
        $report = $action->execute($contract);

        return response()->json([
            'success' => true,
            'report' => $report,
        ]);
    }
}
