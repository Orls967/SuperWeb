<?php

declare(strict_types=1);

namespace Modules\Epc\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Epc\Application\Actions\CapitalizeCompletedCipToFixedAssetAction;
use Modules\Epc\Application\Actions\IssueProjectBast1WithRetentionHoldAction;
use Modules\Epc\Application\Actions\RecordWbsProgressAndCipLedgerCostAction;
use Modules\Epc\Application\Services\EpcService;
use Modules\Epc\Domain\Models\EpcCipCapitalization;
use Modules\Epc\Domain\Models\EpcProgressCertificate;
use Modules\Epc\Domain\Models\EpcProject;

class EpcOperationsController extends Controller
{
    public function dashboard(EpcService $service): View
    {
        $audit = $service->auditEpc();

        return view('epc::epc-operations-dashboard', compact('audit'));
    }

    public function claimsIndex(): View
    {
        $certificates = EpcProgressCertificate::with('project')->latest()->take(30)->get();

        return view('epc::wbs-progress-and-claims', compact('certificates'));
    }

    public function recordClaims(Request $request, RecordWbsProgressAndCipLedgerCostAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|string',
            'certificate_number' => 'nullable|string',
            'certified_incremental_progress_pct' => 'required|numeric|min:0.01|max:100',
            'supervising_consultant_name' => 'nullable|string',
        ]);

        $result = $action->execute($validated);

        return redirect()->route('epc.claims.index')->with('success', "Certificate {$result['certificate']->certificate_number} recorded. Gross claim: Rp ".number_format($result['gross_claim_idr'], 0, ',', '.'));
    }

    public function bastIndex(): View
    {
        $projects = EpcProject::latest()->take(30)->get();

        return view('epc::bast-handover-retention', compact('projects'));
    }

    public function issueBast(Request $request, IssueProjectBast1WithRetentionHoldAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|string',
            'bast_number' => 'nullable|string',
            'warranty_period_days' => 'nullable|integer|min:30',
            'notes' => 'nullable|string',
        ]);

        $result = $action->execute($validated);

        return redirect()->route('epc.bast.index')->with('success', "BAST-1 {$result['bast_number']} issued. Retention warranty held: Rp ".number_format($result['retention_warranty_held_idr'], 0, ',', '.'));
    }

    public function capitalizationIndex(): View
    {
        $capitalizations = EpcCipCapitalization::with('project')->latest()->take(30)->get();

        return view('epc::cip-asset-capitalization', compact('capitalizations'));
    }

    public function capitalize(Request $request, CapitalizeCompletedCipToFixedAssetAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|string',
            'bast_number' => 'nullable|string',
            'target_asset_category' => 'nullable|string',
            'created_asset_id' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $cap = $action->execute($validated);

        return redirect()->route('epc.capitalization.index')->with('success', "CIP capitalized into Fixed Asset {$cap->created_asset_id}. Total cost: Rp ".number_format($cap->total_cip_cost_idr, 0, ',', '.'));
    }
}
