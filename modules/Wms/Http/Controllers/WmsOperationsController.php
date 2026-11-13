<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Wms\Application\Actions\PostDirectedPutawayAction;
use Modules\Wms\Application\Actions\PostWavePickingAllocationAction;
use Modules\Wms\Application\Actions\ReceiveInboundShipmentAction;
use Modules\Wms\Application\Actions\RevalueInventoryCostAction;
use Modules\Wms\Application\Services\LotQuarantineService;
use Modules\Wms\Application\Services\WarehouseUtilizationAnalyzer;
use Modules\Wms\Http\Requests\RevalueInventoryCostRequest;
use Modules\Wms\Http\Requests\StoreDirectedPutawayRequest;
use Modules\Wms\Http\Requests\StoreInboundReceivingRequest;
use Modules\Wms\Http\Requests\StoreWavePickingRequest;
use Modules\Wms\Http\ViewModels\WmsOperationsViewModel;

/**
 * Controller tipis untuk operasi WMS lanjutan (handheld tasks, valuation, receiving, quarantine, utilization, CSV).
 */
class WmsOperationsController extends Controller
{
    public function __construct(
        private readonly WmsOperationsViewModel $viewModel,
    ) {}

    public function tasks(): View
    {
        return view('wms::tasks', $this->viewModel->handheldTasksData());
    }

    public function valuation(): View
    {
        return view('wms::valuation', $this->viewModel->valuationSummary());
    }

    public function receiving(): View
    {
        return view('wms::receiving', $this->viewModel->receivingData());
    }

    public function quarantine(): View
    {
        return view('wms::quarantine', $this->viewModel->quarantineData());
    }

    public function utilization(): View
    {
        return view('wms::utilization', $this->viewModel->utilizationData());
    }

    public function executePutaway(StoreDirectedPutawayRequest $request, PostDirectedPutawayAction $action): RedirectResponse
    {
        $v = $request->validated();
        $action->execute(
            warehouseId: (int) $v['warehouse_id'],
            productId: (int) $v['product_id'],
            qtyBase: (int) $v['qty_base'],
            abcClass: (string) $v['abc_class'],
            unitCostIdr: (int) $v['unit_cost_idr'],
            referenceId: (string) $v['reference_id'],
            actorUserId: $request->user()?->id,
            lotNumber: $v['lot_number'] ?? null,
            expiryDate: $v['expiry_date'] ?? null,
        );

        return back()->with('status', 'Directed putaway berhasil dieksekusi.');
    }

    public function allocateWavePicking(StoreWavePickingRequest $request, PostWavePickingAllocationAction $action): RedirectResponse
    {
        $v = $request->validated();
        $action->execute(
            warehouseId: (int) $v['warehouse_id'],
            waveReference: (string) $v['wave_reference'],
            lines: $v['lines'],
            actorUserId: $request->user()?->id,
        );

        return back()->with('status', 'Wave picking berhasil dialokasikan.');
    }

    public function receiveInbound(StoreInboundReceivingRequest $request, ReceiveInboundShipmentAction $action): RedirectResponse
    {
        $v = $request->validated();
        $v['received_by_user_id'] = $request->user()?->id;
        $action->execute($v);

        return back()->with('status', 'Penerimaan inbound GRN dicatat.');
    }

    public function revalueCost(RevalueInventoryCostRequest $request, RevalueInventoryCostAction $action): RedirectResponse
    {
        $v = $request->validated();
        $action->execute(
            productId: (int) $v['product_id'],
            warehouseId: (int) $v['warehouse_id'],
            newCostIdr: (int) $v['new_cost_idr'],
            method: (string) $v['method'],
            actorUserId: $request->user()?->id,
        );

        return back()->with('status', 'Revaluasi harga pokok berhasil diposting.');
    }

    public function releaseQuarantine(int $binStockId, LotQuarantineService $service): RedirectResponse
    {
        $service->releaseFromQuarantine($binStockId);

        return back()->with('status', 'Stok dilepas dari karantina.');
    }

    public function captureUtilization(int $warehouseId, WarehouseUtilizationAnalyzer $analyzer): RedirectResponse
    {
        $analyzer->captureSnapshot($warehouseId);

        return back()->with('status', 'Snapshot utilisasi gudang diperbarui.');
    }

    public function exportValuationCsv(): Response
    {
        $summary = $this->viewModel->valuationSummary();
        $lines = ['warehouse_id,warehouse_code,account_code,subledger_value_idr,ledger_balance_idr,discrepancy_idr'];
        foreach ($summary['warehouses'] as $row) {
            $lines[] = implode(',', [
                $row['warehouse_id'],
                $row['warehouse_code'],
                $row['account_code'],
                $row['subledger_value_idr'],
                $row['ledger_balance_idr'],
                $row['discrepancy_idr'],
            ]);
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wms-valuation.csv"',
        ]);
    }

    public function exportBinStocksCsv(): Response
    {
        $rows = DB::table('wms_bin_stocks')->orderBy('id')->limit(1000)->get();
        $lines = ['id,bin_id,product_id,qty_base,lot_number,expiry_date,stock_status'];
        foreach ($rows as $r) {
            $lines[] = implode(',', [
                $r->id,
                $r->bin_id,
                $r->product_id,
                $r->qty_base,
                $r->lot_number ?? '',
                $r->expiry_date ?? '',
                $r->stock_status ?? 'available',
            ]);
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wms-bin-stocks.csv"',
        ]);
    }
}
