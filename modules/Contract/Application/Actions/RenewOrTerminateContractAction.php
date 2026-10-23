<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractVersion;
use Modules\Shared\Application\BaseAction;

class RenewOrTerminateContractAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): Contract
    {
        return DB::transaction(function () use ($params) {
            $contract = Contract::findOrFail($params['contract_id']);
            $mode = strtolower((string) ($params['mode'] ?? 'renew'));
            $reason = (string) ($params['reason'] ?? 'Routine contract lifecycle operation');
            $actor = (string) ($params['actor'] ?? 'contract_officer');
            $now = Carbon::now();
            $nowIso = $now->toIso8601String();

            $prevHash = $contract->current_hash ?: ContractVersion::GENESIS_HASH;
            $nextSeq = ((int) ContractVersion::where('contract_id', $contract->id)->max('sequence')) + 1;

            if ($mode === 'renew') {
                $months = (int) ($params['renewal_months'] ?? $contract->renewal_period_months ?? 12);
                $newEndDate = Carbon::parse($contract->end_date)->addMonths($months)->toDateString();
                $body = "Contract renewed for {$months} months until {$newEndDate}. Reason: {$reason}";

                $hash = ContractVersion::calculateHash($prevHash, $nextSeq, 'contract_renewal', $body, $nowIso);

                ContractVersion::create([
                    'id' => (string) Str::uuid(),
                    'contract_id' => $contract->id,
                    'sequence' => $nextSeq,
                    'change_type' => 'contract_renewal',
                    'body' => $body,
                    'metadata' => [
                        'mode' => 'renew',
                        'previous_end_date' => (string) $contract->end_date,
                        'new_end_date' => $newEndDate,
                        'reason' => $reason,
                    ],
                    'prev_hash' => $prevHash,
                    'hash' => $hash,
                    'created_by_name' => $actor,
                    'created_at' => $nowIso,
                ]);

                $contract->update([
                    'end_date' => $newEndDate,
                    'status' => ContractStatus::Active,
                    'current_hash' => $hash,
                ]);
            } elseif ($mode === 'terminate') {
                $body = "Contract terminated prematurely at {$nowIso}. Reason: {$reason}";
                $hash = ContractVersion::calculateHash($prevHash, $nextSeq, 'contract_termination', $body, $nowIso);

                ContractVersion::create([
                    'id' => (string) Str::uuid(),
                    'contract_id' => $contract->id,
                    'sequence' => $nextSeq,
                    'change_type' => 'contract_termination',
                    'body' => $body,
                    'metadata' => [
                        'mode' => 'terminate',
                        'terminated_at' => $nowIso,
                        'reason' => $reason,
                    ],
                    'prev_hash' => $prevHash,
                    'hash' => $hash,
                    'created_by_name' => $actor,
                    'created_at' => $nowIso,
                ]);

                $contract->update([
                    'status' => ContractStatus::Terminated,
                    'terminated_at' => $now,
                    'termination_reason' => $reason,
                    'current_hash' => $hash,
                ]);
            } else {
                throw new InvalidArgumentException("Unsupported contract lifecycle mode [{$mode}]. Use 'renew' or 'terminate'.");
            }

            $this->audit(
                action: "contract_{$mode}ed",
                auditable: $contract,
                newValues: ['mode' => $mode, 'reason' => $reason, 'hash' => $hash],
                impactType: 'contract_lifecycle',
            );

            return $contract;
        });
    }
}
