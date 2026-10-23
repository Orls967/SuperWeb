<?php

declare(strict_types=1);

namespace Modules\Med\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Med\Domain\Models\MedTalentContract;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class SettleTalentResidualPayoutAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function execute(string $contractId): MedTalentContract
    {
        $contract = MedTalentContract::findOrFail($contractId);

        if ($contract->payout_status === 'PAID') {
            throw new RuntimeException("Talent contract [{$contract->contract_code}] residual has already been paid.");
        }

        $payoutAmount = (int) $contract->calculated_royalty_minor;

        return DB::transaction(function () use ($contract, $payoutAmount) {
            $contract->update(['payout_status' => 'PAID']);

            if ($payoutAmount > 0) {
                $idempotencyKey = hash('sha256', "TALENT-PAYOUT:{$contract->contract_code}:{$payoutAmount}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDIA_TALENT_RESIDUAL_PAYOUT',
                    description: "Talent residual payout for contract {$contract->contract_code}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('med:talent_royalty_payable:IDR', $payoutAmount)
                        ->credit('med:agency_commission_payable:IDR', $payoutAmount)
                        ->toEntries('IDR'),
                    referenceType: 'MED_TALENT_CONTRACT',
                    referenceId: $contract->contract_code,
                ));
            }

            $this->audit(
                action: 'med_talent_payout_settled',
                auditable: $contract,
                newValues: ['payout_amount' => $payoutAmount, 'status' => 'PAID'],
                impactType: 'talent_compensation',
            );

            return $contract->fresh();
        });
    }
}
