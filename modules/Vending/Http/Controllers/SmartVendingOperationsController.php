<?php

declare(strict_types=1);

namespace Modules\Vending\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Vending\Application\Actions\ApplyDynamicNearExpiryMarkdownAction;
use Modules\Vending\Application\Actions\DispenseSmartVendingCartAction;
use Modules\Vending\Application\Actions\ForecastVendingDemandAndWasteAction;
use Modules\Vending\Application\Actions\TriggerAutoReplenishmentPoAction;
use Modules\Vending\Domain\Models\VendingAutoPo;
use Modules\Vending\Domain\Models\VendingDemandForecast;
use Modules\Vending\Domain\Models\VendingTransaction;
use Modules\Vending\Domain\Models\VendingUnit;

class SmartVendingOperationsController extends Controller
{
    public function demandForecast(Request $request): View
    {
        $forecasts = VendingDemandForecast::latest('forecast_date')->paginate(15);
        $units = VendingUnit::all();

        return view('vending::vending.demand_forecast', compact('forecasts', 'units'));
    }

    public function autoReplenishment(Request $request): View
    {
        $autoPos = VendingAutoPo::latest()->paginate(15);
        $units = VendingUnit::with('restockTasks')->get();

        return view('vending::vending.auto_replenishment', compact('autoPos', 'units'));
    }

    public function cartDispense(Request $request): View
    {
        $transactions = VendingTransaction::with('unit')->latest()->paginate(15);
        $units = VendingUnit::where('status', 'active')->get();

        return view('vending::vending.cart_dispense', compact('transactions', 'units'));
    }

    public function markdowns(Request $request): View
    {
        $units = VendingUnit::all();

        return view('vending::vending.markdowns', compact('units'));
    }

    public function runForecast(
        Request $request,
        ForecastVendingDemandAndWasteAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'outlet_code' => 'required|string|max:50',
            'item_code' => 'required|string|max:50',
            'forecast_date' => 'required|date',
            'footfall_factor' => 'nullable|numeric',
        ]);

        $result = $action->handle(
            $validated['outlet_code'],
            $validated['item_code'],
            $validated['forecast_date'],
            (float) ($validated['footfall_factor'] ?? 1.2)
        );

        return redirect()->route('vending.operations.demand-forecast')
            ->with('success', "Forecast computed: {$result['forecast_qty']} units demand, {$result['waste_forecast_qty']} waste.");
    }

    public function triggerReplenish(
        Request $request,
        TriggerAutoReplenishmentPoAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'vending_unit_id' => 'required|integer',
            'item_code' => 'required|string|max:50',
            'cost_per_unit_idr' => 'nullable|integer|min:0',
        ]);

        $result = $action->handle(
            (int) $validated['vending_unit_id'],
            $validated['item_code'],
            (int) ($validated['cost_per_unit_idr'] ?? 15000)
        );

        return redirect()->route('vending.operations.auto-replenishment')
            ->with('success', "Auto-PO {$result['auto_po']->auto_po_code} generated for {$result['qty_ordered']} units (IDR ".number_format($result['estimated_total_idr']).').');
    }

    public function dispenseCart(
        Request $request,
        DispenseSmartVendingCartAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'vending_unit_id' => 'required|integer',
            'item_code' => 'required|string|max:50',
            'qty' => 'required|integer|min:1',
            'unit_price_idr' => 'required|integer|min:100',
        ]);

        $result = $action->handle(
            (int) $validated['vending_unit_id'],
            $validated['item_code'],
            (int) $validated['qty'],
            (int) $validated['unit_price_idr']
        );

        return redirect()->route('vending.operations.cart-dispense')
            ->with('success', "Dispensed item {$result['tx_code']}. Remaining stock: {$result['remaining_stock']} units.");
    }

    public function calcMarkdown(
        Request $request,
        ApplyDynamicNearExpiryMarkdownAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'item_code' => 'required|string|max:50',
            'original_price_idr' => 'required|integer|min:100',
            'hours_until_expiry' => 'required|integer|min:0',
        ]);

        $result = $action->handle(
            $validated['item_code'],
            (int) $validated['original_price_idr'],
            (int) $validated['hours_until_expiry']
        );

        return redirect()->route('vending.operations.markdowns')
            ->with('success', "Markdown applied: {$result['tier']} ({$result['discount_pct']}% OFF) &rarr; IDR ".number_format($result['marked_down_price_idr']));
    }
}
