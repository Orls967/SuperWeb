<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Egy\Application\Actions\SettlePowerPurchaseAgreementBillingAction;
use Modules\Egy\Domain\Models\PpaContract;

class EgyPowerPurchaseController extends Controller
{
    public function index(): View
    {
        $contracts = PpaContract::query()->latest()->take(20)->get();

        return view('egy::ppa.index', compact('contracts'));
    }

    public function show(string $id): View
    {
        $contract = PpaContract::findOrFail($id);

        return view('egy::ppa.show', compact('contract'));
    }

    public function settle(Request $request, string $id, SettlePowerPurchaseAgreementBillingAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'delivered_kwh' => ['required', 'integer', 'min:1'],
        ]);

        $settledMinor = $action->execute($id, (int) $validated['delivered_kwh']);

        return redirect()->back()->with('success', 'PPA settlement berhasil diproses: Rp '.number_format($settledMinor, 0, ',', '.'));
    }
}
