<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EgyBillController extends Controller
{
    public function __construct(
        protected EnergyGridAndMeteringService $service
    ) {}

    public function index(Request $request): View
    {
        $period = $request->query('period');
        $status = $request->query('status');

        $query = UtilityConsolidatedInvoice::with('lines');

        if ($period) {
            $query->where('period', $period)->orWhere('billing_period', $period);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(20);

        $totalBilledIdr = (int) UtilityConsolidatedInvoice::sum('total_amount_idr');
        $totalDemandChargeIdr = (int) UtilityConsolidatedInvoice::sum('demand_charge_idr');
        $totalNetCreditIdr = (int) UtilityConsolidatedInvoice::sum('net_metering_credit_idr');

        return view('egy::bills.index', [
            'invoices' => $invoices,
            'totalBilledIdr' => $totalBilledIdr,
            'totalDemandChargeIdr' => $totalDemandChargeIdr,
            'totalNetCreditIdr' => $totalNetCreditIdr,
            'currentPeriod' => $period,
            'currentStatus' => $status,
        ]);
    }

    public function show(string $id): View
    {
        $invoice = UtilityConsolidatedInvoice::with('lines')->findOrFail($id);

        return view('egy::bills.show', [
            'invoice' => $invoice,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'string'],
            'period' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'string'],
        ]);

        $this->service->generateBilling(
            propertyId: $validated['property_id'],
            period: $validated['period'],
            idempotencyKey: $validated['idempotency_key'] ?? null
        );

        return redirect()->route('egy.bills.index')->with('success', 'Tagihan utilitas TOU berhasil dihitung dan diterbitkan.');
    }

    public function export(Request $request): StreamedResponse
    {
        $invoices = UtilityConsolidatedInvoice::orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="utility_bills_'.date('YmdHis').'.csv"',
        ];

        $callback = function () use ($invoices) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Invoice Number',
                'Property ID',
                'Customer Party ID',
                'Period',
                'Electricity (Rp)',
                'Demand Charge (Rp)',
                'Net Metering Credit (Rp)',
                'Total Bill (Rp)',
                'Status',
                'Issued Date',
            ]);

            foreach ($invoices as $inv) {
                fputcsv($handle, [
                    $inv->invoice_number,
                    $inv->property_id,
                    $inv->customer_party_id,
                    $inv->period ?? $inv->billing_period,
                    $inv->electricity_charge_minor,
                    $inv->demand_charge_idr,
                    $inv->net_metering_credit_idr,
                    $inv->total_amount_idr,
                    $inv->status,
                    $inv->created_at?->toDateString(),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
