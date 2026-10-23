<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Support\Posting;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractVersion;
use Modules\Shared\Application\BaseAction;

class ResolveContractDisputeArbitrationAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $contract = Contract::findOrFail($params['contract_id']);
            $forum = (string) ($params['arbitration_forum'] ?? $contract->dispute_forum ?? 'BANI Arbitration');
            $terms = (string) $params['settlement_terms'];
            $status = (string) ($params['resolution_status'] ?? 'settled_amicably');
            $awardMinor = (int) ($params['arbitration_award_minor'] ?? 0);
            $arbitrator = (string) ($params['arbitrator_name'] ?? 'Tribunal Chair');

            $nowIso = Carbon::now()->toIso8601String();
            $prevHash = $contract->current_hash ?: ContractVersion::GENESIS_HASH;
            $nextSeq = ((int) ContractVersion::where('contract_id', $contract->id)->max('sequence')) + 1;

            $disputeBody = "Dispute resolved via {$forum} [Status: {$status}]. Arbitrator: {$arbitrator}. Terms: {$terms}. Financial Award: IDR {$awardMinor}";
            $hash = ContractVersion::calculateHash($prevHash, $nextSeq, 'dispute_resolution', $disputeBody, $nowIso);

            ContractVersion::create([
                'id' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'sequence' => $nextSeq,
                'change_type' => 'dispute_resolution',
                'body' => $disputeBody,
                'metadata' => [
                    'forum' => $forum,
                    'status' => $status,
                    'arbitrator' => $arbitrator,
                    'terms' => $terms,
                    'award_minor' => $awardMinor,
                ],
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_by_name' => $arbitrator,
                'created_at' => $nowIso,
            ]);

            $contract->update([
                'dispute_forum' => $forum,
                'current_hash' => $hash,
            ]);

            if ($awardMinor > 0) {
                $idempotencyKey = hash('sha256', "CTR-ARBITRATION:{$contract->contract_number}:{$nextSeq}:{$awardMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: TransactionType::CONTRACT_PAYMENT->value,
                    description: "Arbitration settlement award for {$contract->contract_number}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('asset:test_cash:IDR', $awardMinor)
                        ->credit('revenue:group_conglomerate:IDR', $awardMinor)
                        ->toEntries('IDR'),
                    referenceType: 'CTR_DISPUTE_AWARD',
                    referenceId: $contract->contract_number,
                ));
            }

            $this->audit(
                action: 'contract_dispute_resolved',
                auditable: $contract,
                newValues: ['forum' => $forum, 'status' => $status, 'award_minor' => $awardMinor],
                impactType: 'legal_dispute',
            );

            return [
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'forum' => $forum,
                'status' => $status,
                'award_minor' => $awardMinor,
                'resolution_hash' => $hash,
            ];
        });
    }
}
