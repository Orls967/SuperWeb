<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Support\Posting;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\PenaltyRule;
use Modules\Shared\Application\BaseAction;

class AssessContractSlaBreachPenaltyAction extends BaseAction
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
            $daysLate = (int) $params['days_late'];
            $principalIdr = (int) ($params['principal_amount_idr'] ?? $contract->total_value_idr);

            $penaltyRule = PenaltyRule::where('contract_id', $contract->id)
                ->where('is_active', true)
                ->first();

            $penaltyAmount = 0;
            if ($penaltyRule !== null) {
                $penaltyAmount = $penaltyRule->computePenalty($daysLate, $principalIdr);
            } else {
                // Default fallback: 100,000 IDR per day late capped at 10% of principal
                $cap = (int) round($principalIdr * 0.10);
                $penaltyAmount = min($cap, $daysLate * 100000);
            }

            if ($penaltyAmount > 0) {
                $idempotencyKey = hash('sha256', "CTR-PENALTY:{$contract->contract_number}:{$daysLate}:{$penaltyAmount}");
                $this->ledgerService->post(new PostingDTO(
                    type: TransactionType::CONTRACT_PENALTY->value,
                    description: "Penetapan denda SLA {$daysLate} hari kontrak {$contract->contract_number}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('asset:test_cash:IDR', $penaltyAmount)
                        ->credit('revenue:group_conglomerate:IDR', $penaltyAmount)
                        ->toEntries('IDR'),
                    referenceType: 'CTR_PENALTY_ASSESSMENT',
                    referenceId: $contract->contract_number,
                ));
            }

            $this->audit(
                action: 'contract_sla_penalty_assessed',
                auditable: $contract,
                newValues: [
                    'days_late' => $daysLate,
                    'principal_idr' => $principalIdr,
                    'penalty_idr' => $penaltyAmount,
                ],
                impactType: 'contract_compliance',
            );

            return [
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'days_late' => $daysLate,
                'principal_amount_idr' => $principalIdr,
                'penalty_amount_idr' => $penaltyAmount,
                'rule_applied' => $penaltyRule?->name ?? 'Default standard late policy',
            ];
        });
    }
}
