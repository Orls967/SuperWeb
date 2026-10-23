<?php

declare(strict_types=1);

namespace Modules\Tlx\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Tlx\Application\Actions\ActivateBulkIotEsimProfileAction;
use Modules\Tlx\Application\Actions\ProvisionEnterprise5GNetworkSliceAction;
use Modules\Tlx\Domain\Models\IotDevice;
use Modules\Tlx\Domain\Models\SimSubscription;
use Modules\Tlx\Domain\Models\TelecomLink;
use Modules\Tlx\Domain\Models\TelecomSite;

class TlxNetworkSliceController extends Controller
{
    public function index(): View
    {
        $sites = TelecomSite::query()->latest()->take(10)->get();
        $links = TelecomLink::query()->latest()->take(10)->get();
        $sims = SimSubscription::query()->latest()->take(10)->get();
        $devices = IotDevice::query()->latest()->take(10)->get();

        return view('tlx::network.index', compact('sites', 'links', 'sims', 'devices'));
    }

    public function slices(): View
    {
        $links = TelecomLink::query()->latest()->take(20)->get();

        return view('tlx::network.slices', compact('links'));
    }

    public function provisionSlice(Request $request, ProvisionEnterprise5GNetworkSliceAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'link_id' => ['required', 'string'],
            'slice_name' => ['required', 'string', 'max:100'],
            'enterprise_entity_id' => ['required', 'string'],
            'slice_bandwidth_gbps' => ['required', 'numeric', 'min:0.1'],
            'monthly_fee_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $slice = $action->execute($validated);

        return redirect()->route('tlx.network.slices')->with('success', "5G Slice berhasil dialokasikan [{$slice['slice_code']}].");
    }

    public function activateEsim(Request $request, ActivateBulkIotEsimProfileAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'client_tenant_id' => ['required', 'string'],
            'profile_type' => ['required', 'string'],
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'data_quota_mb' => ['nullable', 'integer', 'min:100'],
        ]);

        $results = $action->execute($validated);

        return redirect()->route('tlx.network.index')->with('success', count($results).' eSIM IoT profile berhasil diaktivasi.');
    }
}
