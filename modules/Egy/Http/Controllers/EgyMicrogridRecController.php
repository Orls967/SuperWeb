<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Egy\Application\Actions\DispatchMicrogridBatteryPeakShavingAction;
use Modules\Egy\Application\Actions\MintRenewableEnergyCertificateRecAction;
use Modules\Egy\Application\Actions\TradePeerToPeerSolarSurplusAction;
use Modules\Egy\Domain\Models\BessArbitrageRun;
use Modules\Egy\Domain\Models\RenewableEnergyCertificate;

class EgyMicrogridRecController extends Controller
{
    public function index(): View
    {
        $bessRuns = BessArbitrageRun::query()->latest()->take(15)->get();

        return view('egy::microgrid.index', compact('bessRuns'));
    }

    public function recIndex(): View
    {
        $recs = RenewableEnergyCertificate::query()->latest()->take(20)->get();

        return view('egy::rec.index', compact('recs'));
    }

    public function dispatchBattery(Request $request, DispatchMicrogridBatteryPeakShavingAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'asset_id' => ['required', 'string'],
            'discharge_kwh' => ['required', 'integer', 'min:1'],
        ]);

        $run = $action->execute($validated);

        return redirect()->back()->with('success', "Dispatched BESS [{$run->run_code}]: Keuntungan Rp ".number_format($run->net_profit_minor, 0, ',', '.'));
    }

    public function mintRec(Request $request, MintRenewableEnergyCertificateRecAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'generation_asset_id' => ['required', 'string'],
            'mwh_generated' => ['required', 'integer', 'min:1'],
            'vintage_year' => ['nullable', 'integer'],
        ]);

        $rec = $action->execute($validated);

        return redirect()->back()->with('success', "REC Diterbitkan [{$rec->certificate_code}].");
    }

    public function tradeP2p(Request $request, TradePeerToPeerSolarSurplusAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'seller_party_id' => ['required', 'string'],
            'buyer_party_id' => ['required', 'string'],
            'surplus_kwh' => ['required', 'integer', 'min:1'],
            'clearing_price_minor' => ['nullable', 'integer'],
        ]);

        $result = $action->execute($validated);

        return redirect()->back()->with('success', "Trade P2P Solar Cleared [{$result['trade_code']}]: Total Rp ".number_format($result['total_amount_minor'], 0, ',', '.'));
    }
}
