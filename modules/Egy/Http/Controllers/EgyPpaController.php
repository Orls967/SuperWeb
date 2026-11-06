<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Domain\Models\PpaContract;

class EgyPpaController extends Controller
{
    public function __construct(
        protected EnergyGridAndMeteringService $service
    ) {}

    public function index(Request $request): View
    {
        $contracts = PpaContract::with('settlements')
            ->orderBy('contract_number')
            ->paginate(15);

        $totalCapacityKw = (int) PpaContract::where('status', 'active')->sum('capacity_kw');
        $totalSettlementIdr = (int) PpaContract::sum('total_settlement_minor');

        return view('egy::ppa.index', [
            'contracts' => $contracts,
            'totalCapacityKw' => $totalCapacityKw,
            'totalSettlementIdr' => $totalSettlementIdr,
        ]);
    }

    public function show(string $id): View
    {
        $contract = PpaContract::with('settlements')->findOrFail($id);

        return view('egy::ppa.show', [
            'contract' => $contract,
        ]);
    }

    public function settle(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'string'],
            'delivered_kwh' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string'],
        ]);

        $this->service->settlePpa(
            ppaContractId: $id,
            period: $validated['period'],
            deliveredKwh: (int) $validated['delivered_kwh'],
            idempotencyKey: $validated['idempotency_key'] ?? null
        );

        return redirect()->route('egy.ppa.show', $id)->with('success', 'PPA settlement berhasil diproses dan dibukukan ke ledger.');
    }
}
