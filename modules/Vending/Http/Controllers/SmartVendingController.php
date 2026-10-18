<?php

declare(strict_types=1);

namespace Modules\Vending\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Vending\Application\Services\SmartVendingService;
use Modules\Vending\Domain\Models\VendingAutoPo;
use Modules\Vending\Domain\Models\VendingDemandForecast;
use Modules\Vending\Domain\Models\VendingRestockTask;
use Modules\Vending\Domain\Models\VendingTransaction;
use Modules\Vending\Domain\Models\VendingUnit;

class SmartVendingController extends Controller
{
    public function __construct(
        protected SmartVendingService $vendingService
    ) {}

    public function index(): View
    {
        $units = VendingUnit::with(['transactions', 'restockTasks'])->get();
        $forecasts = VendingDemandForecast::latest()->take(10)->get();
        $autoPos = VendingAutoPo::latest()->take(10)->get();
        $transactions = VendingTransaction::with('unit')->latest()->take(10)->get();
        $restockTasks = VendingRestockTask::with('unit')->latest()->take(10)->get();

        return view('vending::vending.index', [
            'units' => $units,
            'forecasts' => $forecasts,
            'autoPos' => $autoPos,
            'transactions' => $transactions,
            'restockTasks' => $restockTasks,
        ]);
    }

    public function dispense(Request $request, VendingUnit $unit): RedirectResponse
    {
        $validated = $request->validate([
            'item_code' => 'required|string|max:32',
            'qty' => 'required|integer|min:1',
            'price_idr' => 'required|integer|min:1000',
            'payment_method' => 'nullable|string|in:QRIS,BIOMETRIC_FACE',
        ]);

        try {
            $this->vendingService->processVendingSale(
                unit: $unit,
                itemCode: $validated['item_code'],
                qty: (int) $validated['qty'],
                priceIdr: (int) $validated['price_idr'],
                paymentMethod: $validated['payment_method'] ?? 'QRIS'
            );

            return redirect()->route('vending.index')->with('success', 'Smart vending transaction dispensed successfully.');
        } catch (\Throwable $e) {
            return redirect()->route('vending.index')->with('error', $e->getMessage());
        }
    }
}
