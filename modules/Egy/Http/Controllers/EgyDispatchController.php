<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Domain\Models\DispatchRun;
use Modules\Egy\Domain\Models\GenerationAsset;

class EgyDispatchController extends Controller
{
    public function __construct(
        protected EnergyGridAndMeteringService $service
    ) {}

    public function index(Request $request): View
    {
        $runs = DispatchRun::with('orders.asset')
            ->orderBy('hour_timestamp', 'desc')
            ->paginate(15);

        $assets = GenerationAsset::orderBy('marginal_cost_idr')->get();
        $totalCurtailedMw = (int) DispatchRun::sum('total_curtailed_mw');
        $totalCostThousandIdr = (int) DispatchRun::sum('total_cost_thousand_idr');

        return view('egy::dispatch.index', [
            'runs' => $runs,
            'assets' => $assets,
            'totalCurtailedMw' => $totalCurtailedMw,
            'totalCostThousandIdr' => $totalCostThousandIdr,
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hour_timestamp' => ['required', 'date'],
            'load_mw' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string'],
        ]);

        $this->service->runDispatch(
            hourTimestamp: $validated['hour_timestamp'],
            loadMw: (int) $validated['load_mw'],
            idempotencyKey: $validated['idempotency_key'] ?? null
        );

        return redirect()->route('egy.dispatch.index')->with('success', 'Hourly dispatch berhasil dijalankan.');
    }
}
