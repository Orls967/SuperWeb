<?php

declare(strict_types=1);

namespace Modules\Tlx\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Tlx\Application\Actions\AllocateDataCenterRackColocationAction;
use Modules\Tlx\Application\Actions\EnforceDataCenterPueCoolingSlaAction;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\TelecomDataCenter;

class TlxDataCenterColocationController extends Controller
{
    public function index(): View
    {
        $datacenters = TelecomDataCenter::query()->latest()->take(10)->get();
        $contracts = ColocationContract::query()->latest()->take(15)->get();

        return view('tlx::datacenter.index', compact('datacenters', 'contracts'));
    }

    public function racks(): View
    {
        $contracts = ColocationContract::query()->latest()->take(25)->get();

        return view('tlx::datacenter.racks', compact('contracts'));
    }

    public function allocateRack(Request $request, AllocateDataCenterRackColocationAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'dc_id' => ['required', 'string'],
            'tenant_party_id' => ['required', 'string'],
            'rack_units_allocated' => ['required', 'integer', 'min:1'],
            'monthly_rack_fee_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $contract = $action->execute($validated);

        return redirect()->route('tlx.datacenter.index')->with('success', "Kontrak colocation berhasil dibuat [{$contract->contract_number}].");
    }

    public function enforcePue(Request $request, EnforceDataCenterPueCoolingSlaAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'dc_id' => ['required', 'string'],
            'measured_pue' => ['required', 'numeric', 'min:1.0', 'max:3.0'],
            'sla_threshold' => ['nullable', 'numeric'],
        ]);

        $result = $action->execute($validated);

        $status = $result['sla_breached'] ? 'SLA Breached (Penalty applied)' : 'SLA Compliant';

        return redirect()->route('tlx.datacenter.index')->with('success', "Evaluasi PUE [{$result['dc_code']}]: {$status}.");
    }
}
