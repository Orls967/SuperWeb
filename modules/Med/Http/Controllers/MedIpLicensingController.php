<?php

declare(strict_types=1);

namespace Modules\Med\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Med\Application\Actions\DistributeBoxOfficeAndStreamingRoyaltyAction;
use Modules\Med\Application\Actions\LicenseContentRightsToTerritoryAction;
use Modules\Med\Application\Actions\SettleTalentResidualPayoutAction;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Med\Domain\Models\MedProject;
use Modules\Med\Domain\Models\MedTalentContract;

class MedIpLicensingController extends Controller
{
    public function index(): View
    {
        $ipAssets = MedIpAsset::query()->latest()->take(15)->get();
        $licenses = MedIpLicense::query()->latest()->take(15)->get();

        return view('med::licensing.index', compact('ipAssets', 'licenses'));
    }

    public function show(string $id): View
    {
        $ip = MedIpAsset::findOrFail($id);
        $licenses = MedIpLicense::where('ip_id', $ip->id)->get();

        return view('med::licensing.show', compact('ip', 'licenses'));
    }

    public function license(Request $request, LicenseContentRightsToTerritoryAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'ip_id' => ['required', 'string'],
            'licensee_entity_id' => ['required', 'string'],
            'channel' => ['nullable', 'string'],
            'territory' => ['nullable', 'string'],
            'minimum_guarantee_minor' => ['nullable', 'integer'],
        ]);

        $license = $action->execute($validated);

        return redirect()->back()->with('success', "Lisensi IP diterbitkan [{$license->license_code}].");
    }

    public function royaltiesIndex(): View
    {
        $projects = MedProject::query()->latest()->take(10)->get();
        $talentContracts = MedTalentContract::query()->latest()->take(15)->get();

        return view('med::royalties.index', compact('projects', 'talentContracts'));
    }

    public function distributeBoxOffice(Request $request, string $id, DistributeBoxOfficeAndStreamingRoyaltyAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'box_office_revenue_minor' => ['required', 'integer', 'min:1'],
        ]);

        $totalRoyalties = $action->execute($id, (int) $validated['box_office_revenue_minor']);

        return redirect()->back()->with('success', 'Royalti Box Office didistribusikan: Rp '.number_format($totalRoyalties, 0, ',', '.'));
    }

    public function settleTalent(string $contractId, SettleTalentResidualPayoutAction $action): RedirectResponse
    {
        $contract = $action->execute($contractId);

        return redirect()->back()->with('success', "Residual talent dibayarkan [{$contract->contract_code}].");
    }
}
