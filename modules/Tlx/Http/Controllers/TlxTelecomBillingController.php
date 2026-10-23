<?php

declare(strict_types=1);

namespace Modules\Tlx\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Tlx\Application\Actions\RateAndBillTelecomCdrBatchAction;
use Modules\Tlx\Application\Actions\SettleInterCarrierRoamingClearingAction;
use Modules\Tlx\Domain\Models\InterconnectSettlement;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;

class TlxTelecomBillingController extends Controller
{
    public function index(): View
    {
        $invoices = IotConnectivityInvoice::query()->latest()->take(15)->get();
        $settlements = InterconnectSettlement::query()->latest()->take(15)->get();

        return view('tlx::billing.index', compact('invoices', 'settlements'));
    }

    public function roaming(): View
    {
        $settlements = InterconnectSettlement::query()->latest()->take(20)->get();

        return view('tlx::billing.roaming', compact('settlements'));
    }

    public function billCdr(Request $request, RateAndBillTelecomCdrBatchAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'client_tenant_id' => ['required', 'string'],
            'billing_month' => ['required', 'string'],
            'voice_seconds' => ['required', 'integer', 'min:0'],
            'sms_count' => ['required', 'integer', 'min:0'],
            'data_megabytes' => ['required', 'integer', 'min:0'],
        ]);

        $invoice = $action->execute($validated);

        return redirect()->route('tlx.billing.index')->with('success', "CDR Billing Invoice diterbitkan [{$invoice->invoice_number}].");
    }

    public function settleRoaming(Request $request, SettleInterCarrierRoamingClearingAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'partner_operator_code' => ['required', 'string'],
            'period_month' => ['required', 'string'],
            'inbound_minutes' => ['required', 'integer', 'min:0'],
            'outbound_minutes' => ['required', 'integer', 'min:0'],
            'inbound_rate_minor' => ['nullable', 'integer', 'min:0'],
            'outbound_rate_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $settlement = $action->execute($validated);

        return redirect()->route('tlx.billing.roaming')->with('success', "Kliring roaming berhasil diselesaikan [{$settlement->settlement_batch_code}].");
    }
}
