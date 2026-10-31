<?php

declare(strict_types=1);

namespace Modules\Tlx\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\Tlx\Application\Services\ConnectivityBillingService;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;
use Modules\Tlx\Domain\Models\NocAlarm;
use Modules\Tlx\Domain\Models\NocTicket;

class TelecomMvpController extends Controller
{
    public function dashboard(): View
    {
        return view('tlx::dashboard');
    }

    public function nocAlarms(): View
    {
        $alarms = NocAlarm::orderByDesc('raised_at')->take(50)->get();
        $tickets = NocTicket::where('status', 'OPEN')->orderByDesc('opened_at')->get();

        return view('tlx::noc_alarms', compact('alarms', 'tickets'));
    }

    public function datacenterColocation(): View
    {
        $contracts = ColocationContract::orderByDesc('created_at')->take(50)->get();

        return view('tlx::datacenter_colocation', compact('contracts'));
    }

    public function connectivityBilling(): View
    {
        $invoices = IotConnectivityInvoice::orderByDesc('billing_period')->take(50)->get();

        return view('tlx::connectivity_billing', compact('invoices'));
    }

    public function connectivityInvoiceDetail(string $id, ConnectivityBillingService $billingService): View
    {
        $invoice = IotConnectivityInvoice::findOrFail($id);

        if (! $billingService->canAccessInvoice(auth()->user(), $id)) {
            abort(403, 'Unauthorized access to connectivity invoice.');
        }

        return view('tlx::connectivity_invoice_detail', compact('invoice'));
    }
}
