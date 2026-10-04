<?php

declare(strict_types=1);

namespace Modules\Contract\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Contract\Application\Services\ContractFinanceService;
use Modules\Contract\Application\Services\ContractRiskService;
use Modules\Contract\Application\Services\ContractService;
use Modules\Contract\Application\Services\ContractUsageService;
use Modules\Contract\Domain\Enums\ContractPartyRole;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Enums\ContractType;
use Modules\Contract\Domain\Enums\MilestoneStatus;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractAttachment;
use Modules\Contract\Domain\Models\ContractMilestone;
use Modules\Contract\Domain\Models\ContractParty;
use Modules\Contract\Domain\Models\ContractTemplate;
use Modules\Core\Contracts\DocumentStoreInterface;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractService $service,
    ) {}

    /** Contract directory with filters. */
    public function index(Request $request): View
    {
        $query = Contract::with(['legalEntity', 'parties'])
            ->orderByDesc('created_at');

        if ($search = $request->get('q')) {
            $query->where(fn ($q) => $q
                ->where('contract_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
            );
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->get('type')) {
            $query->where('contract_type', $type);
        }

        $contracts = $query->paginate(20)->withQueryString();
        $statuses = ContractStatus::cases();
        $types = ContractType::cases();

        return view('contract::index', compact('contracts', 'statuses', 'types'));
    }

    /** Create contract form. */
    public function create(): View
    {
        $legalEntities = LegalEntity::where('is_active', true)->orderBy('name')->get();
        $templates = ContractTemplate::where('is_active', true)->orderBy('name')->get();
        $types = ContractType::cases();
        $parties = Party::where('is_active', true)->whereNull('merged_into_id')->orderBy('name')->get();
        $roles = ContractPartyRole::cases();

        return view('contract::create', compact('legalEntities', 'templates', 'types', 'parties', 'roles'));
    }

    /** Store new contract. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'legal_entity_id' => 'required|uuid|exists:pty_legal_entities,id',
            'title' => 'required|string|max:255',
            'contract_type' => 'required|string',
            'template_id' => 'nullable|uuid|exists:ctr_contract_templates,id',
            'total_value_idr' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|size:3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notice_period_days' => 'nullable|integer|min:1|max:365',
            'auto_renew' => 'boolean',
            'renewal_period_months' => 'nullable|integer|min:1',
            'governing_law' => 'nullable|string|max:100',
            'dispute_forum' => 'nullable|string|max:100',
            'parties' => 'nullable|array|min:2',
            'parties.*.party_id' => 'required|uuid|exists:pty_parties,id',
            'parties.*.role' => 'required|string',
            'parties.*.signing_order' => 'nullable|integer|min:1',
        ]);

        $data['created_by'] = auth()->id();
        $data['created_by_name'] = auth()->user()?->name ?? 'System';

        $contract = $this->service->createContract($data);

        return redirect()
            ->route('contract.show', $contract)
            ->with('success', "Kontrak [{$contract->contract_number}] berhasil dibuat.");
    }

    /** Contract detail: tabs – overview, parties, clauses, milestones, versions. */
    public function show(Contract $contract): View
    {
        $contract->load([
            'legalEntity',
            'template',
            'parties.party',
            'clauses',
            'milestones',
            'attachments.document',
            'paymentSchedules.milestone',
            'amendments.version',
            'usages',
            'versions' => fn ($q) => $q->orderBy('sequence'),
        ]);

        $financeService = app(ContractFinanceService::class);
        $riskService = app(ContractRiskService::class);
        $usageService = app(ContractUsageService::class);
        $schedules = $contract->paymentSchedules;
        $usage = $usageService->utilization($contract);
        $risk = $riskService->score($contract, persist: false);
        $escalation = $financeService->computeEscalation($contract);

        $allParties = Party::where('is_active', true)->whereNull('merged_into_id')->orderBy('name')->get();
        $roles = ContractPartyRole::cases();
        $statuses = ContractStatus::cases();
        $msStatuses = MilestoneStatus::cases();
        $nextStatus = collect(ContractStatus::cases())
            ->filter(fn ($s) => $contract->status->canTransitionTo($s));

        return view('contract::show', compact(
            'contract', 'allParties', 'roles', 'statuses', 'msStatuses', 'nextStatus', 'schedules', 'usage', 'risk', 'escalation'
        ));
    }

    /** Edit contract draft (title, dates, value etc.). */
    public function edit(Contract $contract): View
    {
        abort_unless(
            in_array($contract->status, [ContractStatus::Draft, ContractStatus::Negotiation]),
            403,
            'Kontrak ini tidak dapat diedit dalam status saat ini.'
        );

        $legalEntities = LegalEntity::where('is_active', true)->orderBy('name')->get();
        $types = ContractType::cases();

        return view('contract::edit', compact('contract', 'legalEntities', 'types'));
    }

    /** Update contract (draft/negotiation only). */
    public function update(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless(
            in_array($contract->status, [ContractStatus::Draft, ContractStatus::Negotiation]),
            403
        );

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'total_value_idr' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notice_period_days' => 'nullable|integer|min:1',
            'auto_renew' => 'boolean',
            'renewal_period_months' => 'nullable|integer|min:1',
            'governing_law' => 'nullable|string|max:100',
            'dispute_forum' => 'nullable|string|max:100',
            'body' => 'nullable|string',
        ]);

        $contract->update(array_filter($data, fn ($v) => $v !== null));

        // If body changed, append new version to hash-chain
        if (! empty($data['body']) && $data['body'] !== $contract->current_body) {
            $this->service->appendVersion(
                contract: $contract,
                newBody: $data['body'],
                changeType: 'amendment',
                metadata: ['updated_fields' => array_keys($data)],
                author: auth()->user()?->name ?? 'User',
            );
        }

        return redirect()
            ->route('contract.show', $contract)
            ->with('success', 'Kontrak berhasil diperbarui.');
    }

    /** State machine transition. */
    public function transition(Request $request, Contract $contract): RedirectResponse
    {
        $data = $request->validate([
            'target_status' => 'required|string',
            'reason' => 'nullable|string|max:1000',
        ]);

        $target = ContractStatus::from($data['target_status']);

        $this->service->transitionStatus(
            contract: $contract,
            targetStatus: $target,
            reason: $data['reason'] ?? null,
            actor: auth()->user(),
        );

        return back()->with('success', "Status kontrak diubah ke [{$target->label()}].");
    }

    /** Submit contract for approval via ApprovalEngine. */
    public function submitApproval(Contract $contract): RedirectResponse
    {
        $this->service->submitForApproval($contract, auth()->user());

        return back()->with('success', 'Kontrak diajukan untuk persetujuan.');
    }

    /** E-sign (simulated) a party's signature. */
    public function signParty(Request $request, Contract $contract, ContractParty $party): RedirectResponse
    {
        $data = $request->validate([
            'signer_name' => 'required|string|max:100',
            'signer_title' => 'required|string|max:100',
        ]);

        $this->service->signContractParty(
            contract: $contract,
            partyEntry: $party,
            signerName: $data['signer_name'],
            signerTitle: $data['signer_title'],
        );

        return back()->with('success', "Tanda tangan [{$data['signer_name']}] berhasil direkam.");
    }

    /** List all versions (hash-chain explorer). */
    public function versions(Contract $contract): View
    {
        $versions = $contract->versions()->orderBy('sequence')->paginate(20);
        $chainValid = true;
        $chainError = null;

        try {
            $this->service->verifyHashChain($contract);
        } catch (\Throwable $e) {
            $chainValid = false;
            $chainError = $e->getMessage();
        }

        return view('contract::versions', compact('contract', 'versions', 'chainValid', 'chainError'));
    }

    /** Diff two versions. */
    public function versionDiff(Request $request, Contract $contract): View
    {
        $data = $request->validate([
            'v1' => 'required|integer|min:1',
            'v2' => 'required|integer|min:1|gt:v1',
        ]);

        $diff = $this->service->getVersionDiff($contract, (int) $data['v1'], (int) $data['v2']);

        return view('contract::version_diff', compact('contract', 'diff'));
    }

    /** Append negotiation version. */
    public function appendVersion(Request $request, Contract $contract): RedirectResponse
    {
        $data = $request->validate([
            'body' => 'required|string|min:10',
            'change_type' => 'nullable|in:negotiation,amendment,clause_update',
            'notes' => 'nullable|string|max:500',
        ]);

        $this->service->appendVersion(
            contract: $contract,
            newBody: $data['body'],
            changeType: $data['change_type'] ?? 'negotiation',
            metadata: ['notes' => $data['notes'] ?? ''],
            author: auth()->user()?->name ?? 'Legal Officer',
        );

        return back()->with('success', 'Versi baru berhasil ditambahkan ke rantai kontrak.');
    }

    /** Add milestone/obligation. */
    public function storeMilestone(Request $request, Contract $contract): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'due_date' => 'required|date',
            'responsible_role' => 'nullable|string|max:50',
            'amount_idr' => 'nullable|integer|min:0',
        ]);

        ContractMilestone::create([
            'contract_id' => $contract->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'],
            'responsible_role' => $data['responsible_role'] ?? 'second_party',
            'status' => MilestoneStatus::Pending->value,
            'amount_idr' => $data['amount_idr'] ?? 0,
        ]);

        return back()->with('success', 'Obligasi/milestone berhasil ditambahkan.');
    }

    /** Mark milestone as completed with proof. */
    public function completeMilestone(Request $request, Contract $contract, ContractMilestone $milestone): RedirectResponse
    {
        $data = $request->validate([
            'completion_notes' => 'nullable|string|max:1000',
            'completion_proof_url' => 'nullable|url|max:500',
        ]);

        $milestone->update([
            'status' => MilestoneStatus::Completed->value,
            'completed_at' => now(),
            'completion_notes' => $data['completion_notes'] ?? null,
            'completion_proof_url' => $data['completion_proof_url'] ?? null,
        ]);

        return back()->with('success', "Milestone [{$milestone->title}] ditandai selesai.");
    }

    /** Add a party to a draft/negotiation contract. */
    public function addParty(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless(
            in_array($contract->status, [ContractStatus::Draft, ContractStatus::Negotiation]),
            403
        );

        $data = $request->validate([
            'party_id' => 'required|uuid|exists:pty_parties,id',
            'role' => 'required|string',
            'signing_order' => 'nullable|integer|min:1',
        ]);

        ContractParty::firstOrCreate(
            ['contract_id' => $contract->id, 'party_id' => $data['party_id'], 'role' => $data['role']],
            ['signing_order' => $data['signing_order'] ?? $contract->parties()->count() + 1],
        );

        return back()->with('success', 'Pihak berhasil ditambahkan ke kontrak.');
    }

    /** Remove party from draft contract. */
    public function removeParty(Contract $contract, ContractParty $party): RedirectResponse
    {
        abort_unless($contract->status === ContractStatus::Draft, 403);
        $party->delete();

        return back()->with('success', 'Pihak dihapus dari kontrak.');
    }

    /** Upload supporting document (stored by Core DocumentStore, linked here). */
    public function storeAttachment(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless(
            in_array($contract->status, [ContractStatus::Draft, ContractStatus::Negotiation]),
            403,
            'Lampiran hanya dapat diubah selama kontrak masih dalam draft/negosiasi.'
        );

        $data = $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,csv,docx,xlsx,txt',
            'label' => 'required|string|max:150',
            'kind' => 'required|in:'.implode(',', ContractAttachment::KINDS),
            'party_id' => 'nullable|integer|exists:ctr_contract_parties,id',
        ]);

        $document = app(DocumentStoreInterface::class)->store(
            file: $request->file('file'),
            filename: $request->file('file')->getClientOriginalName(),
            documentType: 'CONTRACT_'.strtoupper($data['kind']),
            documentable: $contract,
            uploadedBy: auth()->user(),
            metadata: ['contract_number' => $contract->contract_number],
            retentionYears: 7,
        );

        ContractAttachment::create([
            'contract_id' => $contract->id,
            'document_id' => (int) $document->id,
            'contract_party_id' => $data['party_id'] ?? null,
            'legal_entity_id' => $contract->legal_entity_id,
            'label' => $data['label'],
            'kind' => $data['kind'],
        ]);

        return back()->with('success', "Lampiran [{$data['label']}] berhasil diunggah.");
    }

    /** Remove a contract attachment (document in store is archived, not deleted). */
    public function destroyAttachment(Contract $contract, ContractAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->contract_id === $contract->id, 404);
        abort_unless(in_array($contract->status, [ContractStatus::Draft, ContractStatus::Negotiation]), 403);

        $attachment->delete();

        return back()->with('success', 'Lampiran dihapus dari kontrak.');
    }

    /** Dashboard kewajiban jatuh tempo lintas kontrak (28.7). */
    public function obligations(Request $request): View
    {
        $days = (int) $request->integer('days', 30);

        $dueMilestones = ContractMilestone::with(['contract.legalEntity'])
            ->whereIn('status', [MilestoneStatus::Pending->value, MilestoneStatus::InProgress->value])
            ->whereDate('due_date', '<=', now()->addDays($days))
            ->orderBy('due_date')
            ->get();

        $expiring = Contract::whereIn('status', [ContractStatus::Active->value, ContractStatus::Suspended->value])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays($days))
            ->orderBy('end_date')
            ->get();

        // Notice period: jendela pemberitahuan berakhir = end_date - notice_period_days.
        // Hitung di PHP (portabel lintas driver DB) di atas kandidat yang end_date-nya
        // masih dalam horizon pengambilan.
        $noticeDue = Contract::where('status', ContractStatus::Active->value)
            ->whereNotNull('end_date')
            ->orderBy('end_date')
            ->get()
            ->filter(function (Contract $c) use ($days): bool {
                if ($c->end_date === null || $c->notice_period_days === null) {
                    return false;
                }

                return $c->end_date->copy()->subDays($c->notice_period_days)
                    ->lte(now()->addDays($days));
            })
            ->values();

        return view('contract::obligations', compact('dueMilestones', 'expiring', 'noticeDue', 'days'));
    }
}
