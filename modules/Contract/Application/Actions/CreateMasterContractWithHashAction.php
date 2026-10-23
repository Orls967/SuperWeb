<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Enums\ContractType;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractVersion;
use Modules\Shared\Application\BaseAction;

class CreateMasterContractWithHashAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): Contract
    {
        return DB::transaction(function () use ($params) {
            $legalEntityId = (string) $params['legal_entity_id'];
            $title = (string) $params['title'];
            $contractType = $params['contract_type'] instanceof ContractType
                ? $params['contract_type']
                : (ContractType::tryFrom((string) ($params['contract_type'] ?? 'service')) ?? ContractType::Service);

            $totalValueIdr = (int) ($params['total_value_idr'] ?? 0);
            $startDate = (string) ($params['start_date'] ?? Carbon::now()->toDateString());
            $endDate = (string) ($params['end_date'] ?? Carbon::now()->addYear()->toDateString());
            $body = (string) ($params['body'] ?? "Master Contract: {$title}");
            $contractNumber = (string) ($params['contract_number'] ?? ('CTR-'.Carbon::now()->format('Ym').'-'.strtoupper(Str::random(6))));

            $contract = Contract::create([
                'id' => (string) Str::uuid(),
                'contract_number' => $contractNumber,
                'title' => $title,
                'contract_type' => $contractType,
                'status' => ContractStatus::Draft,
                'legal_entity_id' => $legalEntityId,
                'total_value_idr' => $totalValueIdr,
                'currency' => 'IDR',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'current_body' => $body,
                'advance_amount_idr' => (int) ($params['advance_amount_idr'] ?? 0),
                'retention_percent' => (int) ($params['retention_percent'] ?? 0),
                'governing_law' => (string) ($params['governing_law'] ?? 'Indonesia'),
                'dispute_forum' => (string) ($params['dispute_forum'] ?? 'BANI Arbitration'),
            ]);

            $nowIso = Carbon::now()->toIso8601String();
            $hash = ContractVersion::calculateHash(
                prevHash: ContractVersion::GENESIS_HASH,
                sequence: 1,
                changeType: 'genesis_initial',
                body: $body,
                createdAtIso: $nowIso,
            );

            ContractVersion::create([
                'id' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'sequence' => 1,
                'change_type' => 'genesis_initial',
                'body' => $body,

                'metadata' => ['title' => $title, 'created_by' => $params['created_by'] ?? 'system'],
                'prev_hash' => ContractVersion::GENESIS_HASH,
                'hash' => $hash,
                'created_by_name' => (string) ($params['created_by'] ?? 'system'),
                'created_at' => $nowIso,
            ]);

            $contract->update([
                'current_hash' => $hash,
            ]);

            $this->audit(
                action: 'contract_master_created',
                auditable: $contract,
                newValues: ['contract_number' => $contractNumber, 'hash' => $hash],
                impactType: 'contract_lifecycle',
            );

            return $contract;
        });
    }
}
