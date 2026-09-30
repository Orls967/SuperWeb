<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mall\Application\Actions\ApplyLatePenaltiesAction;
use Modules\Mall\Application\Actions\GenerateMonthlyInvoicesAction;
use Modules\Mall\Application\Actions\MallAutoDebitAction;
use Modules\Mall\Application\Queries\AgingReceivableQuery;
use Modules\Mall\Application\Queries\BillingOverviewQuery;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\Property;

class BillingController extends Controller
{
    public function index(
        Request $request,
        BillingOverviewQuery $overviewQuery,
        AgingReceivableQuery $agingQuery
    ): View {
        $period = $request->query('month', date('Y-m'));
        $propertyId = $request->filled('property_id') ? (int) $request->query('property_id') : null;
        $statusFilter = $request->query('status');

        $properties = Property::where('is_active', true)->get();
        $overview = $overviewQuery->execute($period, $propertyId);
        $aging = $agingQuery->execute($propertyId);

        $invoicesQuery = Invoice::with(['tenant', 'lease.unit', 'property'])
            ->where('period_month', $period);

        if ($propertyId !== null) {
            $invoicesQuery->where('property_id', $propertyId);
        }

        if ($statusFilter && $statusFilter !== 'all') {
            $invoicesQuery->where('status', $statusFilter);
        }

        $invoices = $invoicesQuery->orderBy('id', 'desc')->paginate(15);

        return view('mall::billing.index', [
            'period' => $period,
            'propertyId' => $propertyId,
            'statusFilter' => $statusFilter,
            'properties' => $properties,
            'overview' => $overview,
            'aging' => $aging,
            'invoices' => $invoices,
        ]);
    }

    public function show(int $id): View
    {
        $invoice = Invoice::with(['tenant.user', 'lease.unit.zone', 'property', 'lines'])->findOrFail($id);

        return view('mall::billing.show', [
            'invoice' => $invoice,
        ]);
    }

    public function generate(Request $request, GenerateMonthlyInvoicesAction $action): RedirectResponse
    {
        $period = $request->input('month', date('Y-m'));
        $propertyId = $request->filled('property_id') ? (int) $request->input('property_id') : null;

        $invoices = $action->generateAll($period, $propertyId);

        return redirect()->route('mall.billing.index', ['month' => $period])
            ->with('success', 'Berhasil menerbitkan/memperbarui '.count($invoices)." tagihan sewa bulanan untuk periode {$period}.");
    }

    public function applyPenalties(ApplyLatePenaltiesAction $action): RedirectResponse
    {
        $result = $action->execute();

        return redirect()->back()->with(
            'success',
            "Denda berhasil diterapkan pada {$result['penalized_invoices']} tagihan terlambat. ({$result['suspended_leases']} lease disuspend)."
        );
    }

    public function autoDebit(int $id, MallAutoDebitAction $action): RedirectResponse
    {
        $invoice = Invoice::with(['tenant.user', 'lines'])->findOrFail($id);
        $result = $action->execute($invoice);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }
}
