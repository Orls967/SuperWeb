<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Syariah\Application\Services\HalalTransactionFilterService;
use Modules\Syariah\Application\Services\NpfClassificationService;
use Modules\Syariah\Application\Services\SyariahOperationsCommandCenterService;
use Modules\Syariah\Application\Services\SyariahRestructuringService;
use Modules\Syariah\Application\Services\UniversalSyariahWalletService;
use Modules\Syariah\Domain\Models\SybDpsComplianceReview;
use Modules\Syariah\Domain\Models\SybHalalCategory;
use Modules\Syariah\Domain\Models\SybHalalMerchant;
use Modules\Syariah\Domain\Models\SybNpfClassification;
use Modules\Syariah\Domain\Models\SybOpsIncidentLog;
use Modules\Syariah\Domain\Models\SybRestructuringRequest;
use Modules\Syariah\Domain\Models\SybUniversalWalletTransaction;

final class SyariahOperationsController extends Controller
{
    public function __construct(
        private readonly SyariahOperationsCommandCenterService $commandCenter,
        private readonly SyariahRestructuringService $restructuringService,
        private readonly NpfClassificationService $npfService,
        private readonly UniversalSyariahWalletService $walletService,
        private readonly HalalTransactionFilterService $filterService,
    ) {}

    public function index(): View
    {
        $metrics = $this->commandCenter->getDashboardMetrics();

        return view('syariah::ops.index', [
            'metrics' => $metrics,
        ]);
    }

    public function npfIndex(): View
    {
        $classifications = SybNpfClassification::orderByDesc('id')->paginate(15);

        return view('syariah::ops.npf_index', [
            'classifications' => $classifications,
        ]);
    }

    public function npfShow(int $id): View
    {
        $classification = SybNpfClassification::with('snapshots')->findOrFail($id);

        return view('syariah::ops.npf_show', [
            'classification' => $classification,
        ]);
    }

    public function restructuringIndex(Request $request): View
    {
        $query = SybRestructuringRequest::query();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $requests = $query->orderByDesc('id')->paginate(15);

        return view('syariah::ops.restructuring_index', [
            'requests' => $requests,
        ]);
    }

    public function restructuringShow(int $id): View
    {
        $requestModel = SybRestructuringRequest::with('installments')->findOrFail($id);

        return view('syariah::ops.restructuring_show', [
            'req' => $requestModel,
        ]);
    }

    public function storeRestructuring(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'financing_ref' => ['required', 'string', 'max:64'],
            'customer_ref' => ['required', 'string', 'max:64'],
            'contract_type' => ['required', 'string', 'max:32'],
            'original_principal_minor' => ['required', 'integer', 'min:1'],
            'original_margin_minor' => ['required', 'integer', 'min:0'],
            'margin_haircut_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'original_tenor_months' => ['required', 'integer', 'min:1'],
            'new_tenor_months' => ['required', 'integer', 'min:1'],
            'grace_period_months' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $created = $this->restructuringService->requestRestructuring(
            financingRef: $validated['financing_ref'],
            customerRef: $validated['customer_ref'],
            contractType: $validated['contract_type'],
            originalOutstandingPrincipalMinor: (int) $validated['original_principal_minor'],
            originalRemainingMarginMinor: (int) $validated['original_margin_minor'],
            marginHaircutBps: (int) $validated['margin_haircut_bps'],
            originalTenorMonths: (int) $validated['original_tenor_months'],
            newTenorMonths: (int) $validated['new_tenor_months'],
            gracePeriodMonths: (int) $validated['grace_period_months'],
            reason: $validated['reason'],
            requestedBy: (string) ($request->user()?->id ?? 'SYSTEM')
        );

        return redirect()->route('syariah.ops.restructuring.show', $created->id)
            ->with('success', "Permohonan restrukturisasi {$created->request_ref} berhasil dibuat.");
    }

    public function dpsReviewRestructuring(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
            'is_approved' => ['required', 'boolean'],
        ]);

        $req = SybRestructuringRequest::findOrFail($id);
        $verdict = ! empty($validated['is_approved']) ? 'DPS_REVIEWED' : 'REJECTED';

        $this->restructuringService->reviewByDps(
            requestRef: $req->request_ref,
            dpsReviewerRef: (string) ($request->user()?->id ?? 'DPS-BOARD'),
            notes: $validated['notes'],
            verdict: $verdict
        );

        return redirect()->route('syariah.ops.restructuring.show', $req->id)
            ->with('success', "Review DPS atas {$req->request_ref} selesai.");
    }

    public function executeRestructuring(int $id): RedirectResponse
    {
        $req = SybRestructuringRequest::findOrFail($id);

        $this->restructuringService->approve($req->request_ref);
        $this->restructuringService->execute($req->request_ref);

        return redirect()->route('syariah.ops.restructuring.show', $req->id)
            ->with('success', "Restrukturisasi {$req->request_ref} berhasil dieksekusi.");
    }

    public function walletIndex(): View
    {
        $transactions = SybUniversalWalletTransaction::orderByDesc('id')->paginate(20);

        return view('syariah::ops.wallet_index', [
            'transactions' => $transactions,
        ]);
    }

    public function merchantsIndex(): View
    {
        $merchants = SybHalalMerchant::with('category')->orderBy('id')->paginate(20);
        $categories = SybHalalCategory::all();

        return view('syariah::ops.merchants_index', [
            'merchants' => $merchants,
            'categories' => $categories,
        ]);
    }

    public function dpsIndex(): View
    {
        $reviews = SybDpsComplianceReview::orderByDesc('id')->paginate(15);

        return view('syariah::ops.dps_index', [
            'reviews' => $reviews,
        ]);
    }

    public function incidentsIndex(): View
    {
        $incidents = SybOpsIncidentLog::orderByDesc('id')->paginate(20);

        return view('syariah::ops.incidents_index', [
            'incidents' => $incidents,
        ]);
    }
}
