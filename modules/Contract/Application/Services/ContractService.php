<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Enums\ContractType;
use Modules\Contract\Domain\Models\ClauseTemplate;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractClause;
use Modules\Contract\Domain\Models\ContractParty;
use Modules\Contract\Domain\Models\ContractTemplate;
use Modules\Contract\Domain\Models\ContractVersion;
use Modules\Contract\Exceptions\ContractChainCorruptedException;
use Modules\Contract\Exceptions\InsufficientPartiesException;
use Modules\Contract\Exceptions\InvalidContractTransitionException;
use Modules\Contract\Exceptions\MissingTransitionReasonException;
use Modules\Contract\Exceptions\UnapprovedSignAttemptException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;

class ContractService
{
    public function __construct(
        private readonly DocumentNumberingInterface $numberingService,
        private readonly ApprovalEngineInterface $approvalEngine,
    ) {}

    /**
     * Create a new contract with auto gapless numbering.
     *
     * @param  array{
     *     legal_entity_id: string,
     *     title: string,
     *     contract_type: ContractType|string,
     *     template_id?: string|null,
     *     total_value_idr?: int,
     *     currency?: string,
     *     start_date?: string|null,
     *     end_date?: string|null,
     *     notice_period_days?: int,
     *     auto_renew?: bool,
     *     renewal_period_months?: int|null,
     *     governing_law?: string,
     *     advance_amount_idr?: int, retention_percent?: int,
     *     escalation_enabled?: bool, escalation_formula?: string,
     *     escalation_index_code?: string, escalation_index_base?: float,
     *     escalation_cap_percent?: float, arbitration_rules?: string,
     *     dispute_forum?: string,
     *     created_by?: string|null,
     *     parties?: array<array{party_id: string, role: string, signing_order?: int}>,
     *     variables?: array<string, mixed>
     * }  $data
     */
    public function createContract(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            $legalEntity = LegalEntity::findOrFail($data['legal_entity_id']);
            $entityCode = $legalEntity->ledger_prefix ?? 'CTR';

            // 1. Generate Gapless Contract Number via Core
            $contractNumber = $this->numberingService->nextNumber(
                entityCode: $entityCode,
                documentType: 'CTR',
                resetMonthly: false,
                customPrefix: "CTR/{$entityCode}/"
            );

            $contractType = $data['contract_type'] instanceof ContractType
                ? $data['contract_type']
                : ContractType::from((string) $data['contract_type']);

            $contract = Contract::create([
                'contract_number' => $contractNumber,
                'title' => $data['title'],
                'contract_type' => $contractType->value,
                'status' => ContractStatus::Draft->value,
                'legal_entity_id' => $legalEntity->id,
                'template_id' => $data['template_id'] ?? null,
                'total_value_idr' => $data['total_value_idr'] ?? 0,
                'currency' => $data['currency'] ?? 'IDR',
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'notice_period_days' => $data['notice_period_days'] ?? 30,
                'auto_renew' => $data['auto_renew'] ?? false,
                'renewal_period_months' => $data['renewal_period_months'] ?? null,
                'governing_law' => $data['governing_law'] ?? 'Indonesia',
                'dispute_forum' => $data['dispute_forum'] ?? 'BANI Jakarta',
                'created_by' => $data['created_by'] ?? null,
                // 29.1 Keuangan
                'advance_amount_idr' => (int) ($data['advance_amount_idr'] ?? 0),
                'advance_paid_idr' => 0,
                'retention_percent' => (int) ($data['retention_percent'] ?? 0),
                // 29.3 Eskalasi
                'escalation_enabled' => (bool) ($data['escalation_enabled'] ?? false),
                'escalation_formula' => $data['escalation_formula'] ?? null,
                'escalation_index_code' => $data['escalation_index_code'] ?? null,
                'escalation_index_base' => isset($data['escalation_index_base']) ? (float) $data['escalation_index_base'] : null,
                'escalation_cap_percent' => isset($data['escalation_cap_percent']) ? (float) $data['escalation_cap_percent'] : null,
                // 29.7 Kepatuhan
                'arbitration_rules' => $data['arbitration_rules'] ?? null,
            ]);

            // 2. Attach Template Clauses if provided
            $bodyContent = '';
            if (! empty($data['template_id'])) {
                $template = ContractTemplate::find($data['template_id']);
                if ($template && ! empty($template->default_clause_ids)) {
                    $order = 1;
                    $variables = $data['variables'] ?? [];
                    foreach ($template->default_clause_ids as $clauseId) {
                        $clauseTpl = ClauseTemplate::find($clauseId);
                        if ($clauseTpl) {
                            $renderedBody = $clauseTpl->render($variables);
                            ContractClause::create([
                                'contract_id' => $contract->id,
                                'clause_template_id' => $clauseTpl->id,
                                'display_order' => $order++,
                                'title' => $clauseTpl->title,
                                'body' => $renderedBody,
                            ]);
                            $bodyContent .= "## {$clauseTpl->title}\n\n{$renderedBody}\n\n";
                        }
                    }
                }
            }

            if (empty($bodyContent)) {
                $bodyContent = "# {$contract->title}\n\nKontrak perjanjian bisnis resmi antara para pihak.";
            }

            // 3. Attach Parties (must have at least 2 parties on subsequent checks, but allow draft initial party list)
            if (! empty($data['parties'])) {
                foreach ($data['parties'] as $idx => $p) {
                    ContractParty::create([
                        'contract_id' => $contract->id,
                        'party_id' => $p['party_id'],
                        'role' => $p['role'],
                        'signing_order' => $p['signing_order'] ?? ($idx + 1),
                    ]);
                }
            }

            // 4. Initial Version 1 in Hash-Chain
            $createdAtIso = now()->toIso8601String();
            $hash = ContractVersion::calculateHash(
                prevHash: 'GENESIS_CTR_000000000000000000000000000000000000000000000000000000000000',
                sequence: 1,
                changeType: 'creation',
                body: $bodyContent,
                createdAtIso: $createdAtIso
            );

            ContractVersion::create([
                'contract_id' => $contract->id,
                'sequence' => 1,
                'change_type' => 'creation',
                'body' => $bodyContent,
                'metadata' => ['initial' => true],
                'prev_hash' => 'GENESIS_CTR_000000000000000000000000000000000000000000000000000000000000',
                'hash' => $hash,
                'created_by_name' => $data['created_by_name'] ?? 'System',
                'created_at' => $createdAtIso,
            ]);

            $contract->update([
                'current_body' => $bodyContent,
                'current_hash' => $hash,
            ]);

            return $contract->fresh(['parties', 'clauses', 'versions']);
        });
    }

    /**
     * Append a new version to the append-only hash-chain.
     */
    public function appendVersion(
        Contract $contract,
        string $newBody,
        string $changeType = 'negotiation',
        array $metadata = [],
        ?string $author = 'Legal Officer'
    ): ContractVersion {
        return DB::transaction(function () use ($contract, $newBody, $changeType, $metadata, $author) {
            $contract->lockForUpdate();

            // Query DB directly to get authoritative latest sequence (avoids stale in-memory cache)
            $latestVersion = ContractVersion::where('contract_id', $contract->id)
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();
            $prevHash = $latestVersion ? $latestVersion->hash : 'GENESIS_CTR_000000000000000000000000000000000000000000000000000000000000';
            $nextSequence = ($latestVersion?->sequence ?? 0) + 1;
            $createdAtIso = now()->toIso8601String();

            $hash = ContractVersion::calculateHash(
                prevHash: $prevHash,
                sequence: $nextSequence,
                changeType: $changeType,
                body: $newBody,
                createdAtIso: $createdAtIso
            );

            $version = ContractVersion::create([
                'contract_id' => $contract->id,
                'sequence' => $nextSequence,
                'change_type' => $changeType,
                'body' => $newBody,
                'metadata' => $metadata,
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_by_name' => $author,
                'created_at' => $createdAtIso,
            ]);

            $contract->update([
                'current_body' => $newBody,
                'current_hash' => $hash,
            ]);

            return $version;
        });
    }

    /**
     * Verify the entire cryptographic hash-chain for a contract.
     * Returns true if valid, throws ContractChainCorruptedException or returns false.
     */
    public function verifyHashChain(Contract $contract): bool
    {
        $versions = $contract->versions()->orderBy('sequence')->get();
        if ($versions->isEmpty()) {
            return true;
        }

        $expectedPrevHash = 'GENESIS_CTR_000000000000000000000000000000000000000000000000000000000000';

        foreach ($versions as $idx => $v) {
            $expectedSeq = $idx + 1;
            if ($v->sequence !== $expectedSeq) {
                throw new ContractChainCorruptedException("Urutan versi tidak konsisten pada kontrak {$contract->contract_number}: diharapkan {$expectedSeq}, ditemukan {$v->sequence}");
            }

            if ($v->prev_hash !== $expectedPrevHash) {
                throw new ContractChainCorruptedException("Rantai prev_hash rusak pada urutan {$v->sequence} kontrak {$contract->contract_number}");
            }

            $calculatedHash = ContractVersion::calculateHash(
                prevHash: $v->prev_hash,
                sequence: $v->sequence,
                changeType: $v->change_type,
                body: $v->body,
                createdAtIso: $v->created_at->toIso8601String()
            );

            if ($calculatedHash !== $v->hash) {
                throw new ContractChainCorruptedException("Hash digest tidak cocok pada urutan {$v->sequence} kontrak {$contract->contract_number}");
            }

            $expectedPrevHash = $v->hash;
        }

        return true;
    }

    /**
     * Transition contract status with strict state machine and validation rules.
     */
    public function transitionStatus(
        Contract $contract,
        ContractStatus $targetStatus,
        ?string $reason = null,
        ?User $actor = null
    ): Contract {
        return DB::transaction(function () use ($contract, $targetStatus, $reason) {
            $contract->lockForUpdate();

            if (! $contract->status->canTransitionTo($targetStatus)) {
                throw new InvalidContractTransitionException(
                    "Transisi status kontrak dari [{$contract->status->value}] ke [{$targetStatus->value}] tidak diizinkan."
                );
            }

            // Reason mandatory for terminate and suspend
            if (in_array($targetStatus, [ContractStatus::Terminated, ContractStatus::Suspended]) && empty($reason)) {
                throw new MissingTransitionReasonException(
                    'Alasan wajib disertakan untuk melakukan penangguhan (suspend) atau pemutusan (terminate) kontrak.'
                );
            }

            // Must have >= 2 parties before moving beyond Draft
            if ($targetStatus !== ContractStatus::Terminated && $contract->status === ContractStatus::Draft) {
                if ($contract->parties()->count() < 2) {
                    throw new InsufficientPartiesException(
                        'Kontrak wajib memiliki minimal 2 pihak (Pihak Pertama dan Pihak Kedua) sebelum diajukan peninjauan.'
                    );
                }
            }

            // Moving to Signed requires approval first
            if ($targetStatus === ContractStatus::Signed && $contract->status !== ContractStatus::Approved) {
                throw new UnapprovedSignAttemptException(
                    'Kontrak harus berada dalam status Approved sebelum dapat ditandatangani.'
                );
            }

            $updates = ['status' => $targetStatus->value];

            if ($targetStatus === ContractStatus::Suspended) {
                $updates['suspension_reason'] = $reason;
            } elseif ($targetStatus === ContractStatus::Terminated) {
                $updates['terminated_at'] = now();
                $updates['termination_reason'] = $reason;
            } elseif ($targetStatus === ContractStatus::Active) {
                $updates['activated_at'] = now();
            }

            $contract->update($updates);

            return $contract->fresh();
        });
    }

    /**
     * Submit contract for approval via Core ApprovalEngine (multi-level & four-eyes).
     */
    public function submitForApproval(Contract $contract, User $creator): void
    {
        DB::transaction(function () use ($contract, $creator) {
            $contract->lockForUpdate();

            // Guard parties
            if ($contract->parties()->count() < 2) {
                throw new InsufficientPartiesException('Kontrak wajib memiliki minimal 2 pihak sebelum dimintakan persetujuan.');
            }

            // Define approval steps based on contract total value
            $steps = [['role' => 'legal']];
            if ($contract->total_value_idr >= 100_000_000) {
                $steps[] = ['role' => 'admin'];
            }

            $approval = $this->approvalEngine->submit(
                approvalType: 'CONTRACT',
                title: "Persetujuan Kontrak {$contract->contract_number}: {$contract->title}",
                creator: $creator,
                approvable: $contract,
                amount: (float) $contract->total_value_idr,
                steps: $steps,
                slaHours: 72,
                metadata: [
                    'contract_number' => $contract->contract_number,
                    'contract_type' => $contract->contract_type->value,
                    'total_value_idr' => $contract->total_value_idr,
                ]
            );

            $contract->update([
                'approval_id' => $approval->uuid,
                'status' => ContractStatus::Review->value,
            ]);
        });
    }

    /**
     * Simulated digital e-signature execution for a contract party.
     */
    public function signContractParty(
        Contract $contract,
        ContractParty $partyEntry,
        string $signerName,
        string $signerTitle
    ): ContractParty {
        return DB::transaction(function () use ($contract, $partyEntry, $signerName, $signerTitle) {
            $contract->lockForUpdate();
            $partyEntry->lockForUpdate();

            if (! in_array($contract->status, [ContractStatus::Approved, ContractStatus::Signed])) {
                throw new UnapprovedSignAttemptException(
                    "Kontrak belum disetujui untuk ditandatangani. Status saat ini: [{$contract->status->value}]."
                );
            }

            // Generate deterministic cryptographic signature hash
            $timestampIso = now()->toIso8601String();
            $sigData = "SIGN|{$contract->id}|{$contract->current_hash}|{$partyEntry->party_id}|{$signerName}|{$timestampIso}";
            $sigHash = hash('sha256', $sigData);

            $partyEntry->update([
                'is_signed' => true,
                'signed_at' => $timestampIso,
                'signature_hash' => $sigHash,
                'signer_name' => $signerName,
                'signer_title' => $signerTitle,
            ]);

            // Reload parties from DB before checking isFullySigned (avoids stale in-memory cache)
            $contract->unsetRelation('parties');
            if ($contract->isFullySigned()) {
                $contract->update([
                    'status' => ContractStatus::Signed->value,
                    'signed_at' => $timestampIso,
                ]);
            }

            return $partyEntry->fresh();
        });
    }

    /**
     * Compute textual diff between two versions.
     *
     * @return array{v1: int, v2: int, diff: string}
     */
    public function getVersionDiff(Contract $contract, int $v1Seq, int $v2Seq): array
    {
        $v1 = $contract->versions()->where('sequence', $v1Seq)->firstOrFail();
        $v2 = $contract->versions()->where('sequence', $v2Seq)->firstOrFail();

        $lines1 = explode("\n", $v1->body);
        $lines2 = explode("\n", $v2->body);

        $diffLines = [];
        $max = max(count($lines1), count($lines2));
        for ($i = 0; $i < $max; $i++) {
            $l1 = $lines1[$i] ?? null;
            $l2 = $lines2[$i] ?? null;

            if ($l1 !== $l2) {
                if ($l1 !== null) {
                    $diffLines[] = "- {$l1}";
                }
                if ($l2 !== null) {
                    $diffLines[] = "+ {$l2}";
                }
            } else {
                $diffLines[] = "  {$l1}";
            }
        }

        return [
            'v1' => $v1Seq,
            'v2' => $v2Seq,
            'diff' => implode("\n", $diffLines),
        ];
    }
}
