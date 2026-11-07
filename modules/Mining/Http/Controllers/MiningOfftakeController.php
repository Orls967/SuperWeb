<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Mining\Application\Actions\NominateOfftakeVesselAction;
use Modules\Mining\Application\Actions\SettleFinalAssayOfftakeInvoiceAction;
use Modules\Mining\Application\Actions\SettleProvisionalOfftakeInvoiceAction;
use Modules\Mining\Domain\Models\MiningOfftakeContract;
use Modules\Mining\Domain\Models\MiningOfftakeShipment;

class MiningOfftakeController extends Controller
{
    public function index()
    {
        $contracts = MiningOfftakeContract::latest()->take(10)->get();
        $shipments = MiningOfftakeShipment::latest()->take(10)->get();

        return view('mining::offtake', [
            'contracts' => $contracts,
            'shipments' => $shipments,
        ]);
    }

    /**
     * Daftarkan nominasi kapal pengangkut kargo offtake (D94-7).
     */
    public function nominate(Request $request, NominateOfftakeVesselAction $action)
    {
        $validated = $request->validate([
            'contract_id' => 'required|uuid',
            'vessel_name' => 'required|string|max:80',
            'laycan_start' => 'required|date',
            'laycan_end' => 'required|date|after_or_equal:laycan_start',
            'nominated_volume_tons' => 'required|integer|min:1000',
        ]);

        $shipment = $action->execute($validated);

        return redirect()->back()->with('success', "Nominasi kapal {$shipment->vessel_name} berhasil didaftarkan: #{$shipment->shipment_number}");
    }

    /**
     * Penerbitan invoice provisional pengapalan (D94-7).
     */
    public function settleProvisional(Request $request, SettleProvisionalOfftakeInvoiceAction $action)
    {
        $validated = $request->validate([
            'shipment_id' => 'required|uuid',
            'provisional_percentage_bps' => 'nullable|integer|between:5000,9500',
            'exchange_rate_idr' => 'nullable|integer|min:10000',
        ]);

        $result = $action->execute($validated);

        return redirect()->back()->with('success', "Invoice provisional pengapalan #{$result['shipment_number']} berhasil diterbitkan (Ref: #INV-{$result['provisional_invoice_ref']})");
    }

    /**
     * Pelunasan akhir pengapalan assay & kurs settlement (D94-7).
     */
    public function settleFinal(Request $request, SettleFinalAssayOfftakeInvoiceAction $action)
    {
        $validated = $request->validate([
            'shipment_id' => 'required|uuid',
            'actual_gar_kcal' => 'nullable|integer',
            'actual_ash_permil' => 'nullable|integer',
            'actual_sulfur_permil' => 'nullable|integer',
            'settlement_exchange_rate_idr' => 'nullable|integer|min:10000',
        ]);

        $result = $action->execute($validated);

        return redirect()->back()->with('success', "Pelunasan final pengapalan #{$result['shipment_number']} tuntas dibukukan.");
    }
}
