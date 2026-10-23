<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractVersion;
use Modules\Shared\Application\BaseAction;

class ApproveAndSignContractVersionAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): Contract
    {
        return DB::transaction(function () use ($params) {
            $contract = Contract::findOrFail($params['contract_id']);
            $signerName = (string) $params['signer_name'];
            $signerTitle = (string) ($params['signer_title'] ?? 'Authorized Representative');
            $now = Carbon::now();
            $nowIso = $now->toIso8601String();

            $prevHash = $contract->current_hash ?: ContractVersion::GENESIS_HASH;
            $nextSequence = ((int) ContractVersion::where('contract_id', $contract->id)->max('sequence')) + 1;

            $signatureBody = "Contract signed and ratified by {$signerName} ({$signerTitle}) at {$nowIso}";
            $hash = ContractVersion::calculateHash(
                prevHash: $prevHash,
                sequence: $nextSequence,
                changeType: 'signature_ratification',
                body: $signatureBody,
                createdAtIso: $nowIso,
            );

            ContractVersion::create([
                'id' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'sequence' => $nextSequence,
                'change_type' => 'signature_ratification',
                'body' => $signatureBody,
                'metadata' => [
                    'signer_name' => $signerName,
                    'signer_title' => $signerTitle,
                    'timestamp' => $nowIso,
                ],
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_by_name' => $signerName,
                'created_at' => $nowIso,
            ]);

            $contract->update([
                'status' => ContractStatus::Active,
                'current_hash' => $hash,
                'signed_at' => $now,
                'activated_at' => $now,
            ]);

            $this->audit(
                action: 'contract_version_signed_and_active',
                auditable: $contract,
                newValues: ['signer' => $signerName, 'hash' => $hash, 'sequence' => $nextSequence],
                impactType: 'legal_signature',
            );

            return $contract;
        });
    }
}
