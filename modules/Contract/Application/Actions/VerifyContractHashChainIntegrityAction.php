<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Actions;

use Carbon\Carbon;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractVersion;
use Modules\Shared\Application\BaseAction;

class VerifyContractHashChainIntegrityAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{is_valid: bool, versions_verified: int, latest_hash: ?string, errors: list<string>}
     */
    public function execute(array $params): array
    {
        $contract = Contract::findOrFail($params['contract_id']);
        $versions = ContractVersion::where('contract_id', $contract->id)
            ->orderBy('sequence', 'asc')
            ->get();

        $errors = [];
        $expectedPrevHash = ContractVersion::GENESIS_HASH;
        $latestHash = null;

        if ($versions->isEmpty()) {
            return [
                'is_valid' => false,
                'versions_verified' => 0,
                'latest_hash' => null,
                'errors' => ['No contract versions recorded.'],
            ];
        }

        foreach ($versions as $idx => $v) {
            $seq = $v->sequence;

            if ($idx === 0) {
                if ($v->prev_hash !== ContractVersion::GENESIS_HASH && $v->prev_hash !== ContractVersion::LEGACY_GENESIS_HASH) {
                    $errors[] = "Genesis version has invalid prev_hash: {$v->prev_hash}";
                }
            } else {
                if ($v->prev_hash !== $expectedPrevHash) {
                    $errors[] = "Version seq {$seq} prev_hash mismatch. Expected {$expectedPrevHash}, found {$v->prev_hash}";
                }
            }

            $createdAtIso = $v->created_at instanceof Carbon
                ? $v->created_at->toIso8601String()
                : (string) $v->created_at;

            $recomputed = ContractVersion::calculateHash(
                prevHash: $v->prev_hash,
                sequence: $v->sequence,
                changeType: $v->change_type,
                body: $v->body,
                createdAtIso: $createdAtIso,
            );

            if ($recomputed !== $v->hash) {
                $errors[] = "Version seq {$seq} cryptographic tampering detected. Stored: {$v->hash}, Recomputed: {$recomputed}";
            }

            $expectedPrevHash = $v->hash;
            $latestHash = $v->hash;
        }

        if ($contract->current_hash !== null && $contract->current_hash !== $latestHash) {
            $errors[] = "Contract current_hash [{$contract->current_hash}] does not match latest version hash [{$latestHash}].";
        }

        $isValid = empty($errors);

        $this->audit(
            action: 'contract_hash_chain_verified',
            auditable: $contract,
            newValues: [
                'is_valid' => $isValid,
                'versions_verified' => $versions->count(),
                'latest_hash' => $latestHash,
                'error_count' => count($errors),
            ],
            impactType: 'cryptographic_audit',
        );

        return [
            'is_valid' => $isValid,
            'versions_verified' => $versions->count(),
            'latest_hash' => $latestHash,
            'errors' => $errors,
        ];
    }
}
