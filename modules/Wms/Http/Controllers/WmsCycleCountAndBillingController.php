<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Wms\Application\Actions\Calculate3PlStorageHandlingBillingAction;
use Modules\Wms\Application\Actions\RecordCycleCountAdjustmentAction;
use Modules\Wms\Domain\Models\CycleCount;
use Modules\Wms\Domain\Models\Warehouse;

final class WmsCycleCountAndBillingController extends Controller
{
    public function __construct(
        private readonly RecordCycleCountAdjustmentAction $cycleCountAction,
        private readonly Calculate3PlStorageHandlingBillingAction $billingAction
    ) {}

    public function cycleCountIndex(): View
    {
        $counts = CycleCount::query()->with('lines')->latest()->limit(20)->get();
        $warehouses = Warehouse::query()->where('is_active', true)->get();

        return view('wms::cycle_count', compact('counts', 'warehouses'));
    }

    public function billingIndex(): View
    {
        $warehouses = Warehouse::query()->where('is_active', true)->get();

        return view('wms::billing_3pl', compact('warehouses'));
    }

    public function dashboardIndex(): View
    {
        $warehouses = Warehouse::query()->withCount('zones')->get();

        return view('wms::operations_dashboard', compact('warehouses'));
    }

    public function recordAdjustment(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'required|string|exists:wms_warehouses,id',
            'lines' => 'required|array|min:1',
            'lines.*.bin_id' => 'required|integer',
            'lines.*.product_id' => 'required|integer',
            'lines.*.counted_qty' => 'required|numeric|min:0',
            'counter_id' => 'required|integer|exists:users,id',
            'approver_id' => 'required|integer|exists:users,id',
        ]);

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $counter = User::findOrFail($data['counter_id']);
        $approver = User::findOrFail($data['approver_id']);

        $adjustedCount = $this->cycleCountAction->execute(
            warehouse: $warehouse,
            lines: $data['lines'],
            counter: $counter,
            approver: $approver
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'count_id' => $adjustedCount->id,
                'status' => $adjustedCount->status,
                'variance_qty' => $adjustedCount->variance_qty,
            ]);
        }

        return redirect()->back()->with('success', "Cycle count {$adjustedCount->number} berhasil diproses.");
    }

    public function compute3PlBilling(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'required|string|exists:wms_warehouses,id',
            'client_id' => 'required|integer',
            'pallet_count' => 'required|integer|min:0',
            'storage_days' => 'required|integer|min:0',
            'storage_rate_per_day_idr' => 'required|integer|min:0',
            'inbound_units' => 'required|integer|min:0',
            'handling_in_rate_idr' => 'required|integer|min:0',
            'outbound_units' => 'required|integer|min:0',
            'handling_out_rate_idr' => 'required|integer|min:0',
            'vas_fee_idr' => 'nullable|integer|min:0',
        ]);

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $result = $this->billingAction->execute($warehouse, (int) $data['client_id'], [
            'pallet_count' => (int) $data['pallet_count'],
            'storage_days' => (int) $data['storage_days'],
            'storage_rate_per_day_idr' => (int) $data['storage_rate_per_day_idr'],
            'inbound_units' => (int) $data['inbound_units'],
            'handling_in_rate_idr' => (int) $data['handling_in_rate_idr'],
            'outbound_units' => (int) $data['outbound_units'],
            'handling_out_rate_idr' => (int) $data['handling_out_rate_idr'],
            'vas_fee_idr' => (int) ($data['vas_fee_idr'] ?? 0),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'billing' => $result,
            ]);
        }

        return redirect()->back()->with('billing_result', $result);
    }
}
