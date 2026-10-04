<?php

declare(strict_types=1);

namespace Modules\Party\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Party\Application\Actions\ApproveKycDocumentAction;
use Modules\Party\Application\Actions\RejectKycDocumentAction;
use Modules\Party\Application\Actions\SubmitKycDocumentAction;
use Modules\Party\Application\Services\CreditScoringService;
use Modules\Party\Application\Services\PartyService;
use Modules\Party\Application\Services\SanctionScreeningService;
use Modules\Party\Domain\Models\KycDocument;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;

class PartyController extends Controller
{
    public function __construct(
        private readonly PartyService $partyService,
        private readonly SanctionScreeningService $screeningService,
        private readonly CreditScoringService $creditService,
    ) {}

    /** Directory: list all active parties. */
    public function index(Request $request): View
    {
        $query = Party::with(['roles', 'creditProfile'])
            ->where('is_active', true)
            ->whereNull('merged_into_id');

        if ($search = $request->get('q')) {
            $norm = strtolower(trim($search));
            $query->where(fn ($q) => $q
                ->where('name_normalized', 'like', "%{$norm}%")
                ->orWhere('nib', 'like', "%{$search}%")
            );
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($role = $request->get('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('role', $role)->where('is_active', true));
        }

        $parties = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('party::index', compact('parties'));
    }

    /** Create form. */
    public function create(): View
    {
        $legalEntities = LegalEntity::where('is_active', true)->orderBy('name')->get();

        return view('party::create', compact('legalEntities'));
    }

    /** Store new party. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:person,company',
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'legal_entity_id' => 'nullable|uuid|exists:pty_legal_entities,id',
            'npwp' => 'nullable|string|max:30',
            'nik' => 'nullable|string|max:20',
            'nib' => 'nullable|string|max:20',
            'role' => 'nullable|string',
            'credit_limit_idr' => 'nullable|integer|min:0',
        ]);

        $party = $this->partyService->create($data);

        // Run initial sanctions screening
        $this->screeningService->screen($party, 'onboarding');

        return redirect()->route('party.show', $party)->with('success', "Party [{$party->name}] berhasil dibuat.");
    }

    /** Detail 360° view. */
    public function show(Party $party): View
    {
        $party->load([
            'legalEntity', 'roles', 'addresses', 'contacts',
            'bankAccounts', 'kycDocuments', 'sanctionsChecks', 'creditProfile',
        ]);

        return view('party::show', compact('party'));
    }

    /** Submit a KYC document. */
    public function submitKyc(Request $request, Party $party, SubmitKycDocumentAction $action): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => 'required|string',
            'document_number' => 'nullable|string|max:100',
            'issuer' => 'nullable|string|max:100',
            'issued_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $action->execute($party, $data);

        return back()->with('success', 'Dokumen KYC berhasil diajukan.');
    }

    /** Approve KYC document (admin). */
    public function approveKyc(Party $party, KycDocument $doc, ApproveKycDocumentAction $action): RedirectResponse
    {
        $action->execute($doc, auth()->user()?->name);

        return back()->with('success', 'Dokumen disetujui.');
    }

    /** Reject KYC document (admin). */
    public function rejectKyc(Request $request, Party $party, KycDocument $doc, RejectKycDocumentAction $action): RedirectResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $action->execute($doc, $request->reason);

        return back()->with('success', 'Dokumen ditolak.');
    }

    /** Run sanctions screening manually. */
    public function screen(Party $party): RedirectResponse
    {
        $this->screeningService->screen($party, 'manual');

        return back()->with('success', 'Sanctions screening selesai.');
    }

    /** Recalculate credit score. */
    public function rescoreCredit(Party $party): RedirectResponse
    {
        $this->creditService->score($party);

        return back()->with('success', 'Credit profile diperbarui.');
    }

    /** Legal entity directory. */
    public function legalEntities(): View
    {
        $entities = LegalEntity::with('children')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->get();

        return view('party::legal_entities', compact('entities'));
    }

    /** Store legal entity. */
    public function storeLegalEntity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'entity_type' => 'required|in:company,subsidiary,branch',
            'parent_id' => 'nullable|uuid|exists:pty_legal_entities,id',
            'npwp' => 'nullable|string|max:30',
            'nib' => 'nullable|string|max:20',
            'functional_currency' => 'required|string|size:3',
            'fiscal_year_start' => 'required|string|max:5',
        ]);

        LegalEntity::create($data + ['is_active' => true]);

        return redirect()->route('party.legal-entities')->with('success', 'Legal entity berhasil ditambahkan.');
    }
}
