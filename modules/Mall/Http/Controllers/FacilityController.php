<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mall\Application\Actions\BillWorkOrderToTenantAction;
use Modules\Mall\Application\Actions\GeneratePmWorkOrdersAction;
use Modules\Mall\Application\Queries\FacilityKanbanQuery;
use Modules\Mall\Domain\Models\WorkOrder;

class FacilityController extends Controller
{
    public function index(FacilityKanbanQuery $query): View
    {
        $data = $query->get();

        return view('mall::facilities.index', [
            'kanban' => $data['kanban'],
            'assetStats' => $data['asset_stats'],
            'slaBreachesCount' => $data['sla_breaches_count'],
        ]);
    }

    public function generatePm(Request $request, GeneratePmWorkOrdersAction $action): RedirectResponse
    {
        try {
            $orders = $action->execute();

            return back()->with('success', "Pemeliharaan preventif diproses: {$orders->count()} SPK / Work Order telah diterbitkan.");
        } catch (\Throwable $e) {
            return back()->withErrors(['pm_error' => $e->getMessage()]);
        }
    }

    public function billToTenant(
        Request $request,
        WorkOrder $workOrder,
        BillWorkOrderToTenantAction $action
    ): RedirectResponse {
        try {
            $invoice = $action->execute($workOrder);

            return back()->with('success', 'Biaya perbaikan Rp '.number_format($workOrder->total_cost)." berhasil ditambahkan ke Invoice #{$invoice->invoice_number}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['bill_error' => $e->getMessage()]);
        }
    }
}
