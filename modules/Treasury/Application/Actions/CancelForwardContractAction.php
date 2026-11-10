<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\StateMachines\HedgeContractStateMachine;

class CancelForwardContractAction
{
    public function __construct(
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly HedgeContractStateMachine $stateMachine = new HedgeContractStateMachine,
    ) {}

    /**
     * Membatalkan kontrak forward dengan pencatatan denda pembatalan perbankan.
     */
    public function execute(
        ForwardContract $contract,
        int $actorId,
        string $reason,
        int $cancellationFeeIdr = 5_000_000,
        string $bankAccountCode = 'trs:bank_operational:HOLDING:IDR'
    ): ForwardContract {
        return DB::transaction(function () use ($contract, $actorId, $reason, $cancellationFeeIdr, $bankAccountCode) {
            $fromStatus = $contract->status instanceof HedgeStatus ? $contract->status->value : (string) $contract->status;

            if (in_array($fromStatus, [HedgeStatus::SETTLED->value, HedgeStatus::ROLLED->value, HedgeStatus::CANCELLED->value], true)) {
                throw new InvalidArgumentException("Kontrak forward {$contract->contract_number} tidak dapat dibatalkan dari status {$fromStatus}.");
            }

            $contract->update([
                'cancellation_fee_idr' => $cancellationFeeIdr,
                'rejection_reason' => $reason,
            ]);

            // Transisi ke CANCELLED via state machine
            $cancelled = $this->stateMachine->transition(
                $contract,
                HedgeStatus::CANCELLED,
                $actorId,
                $reason
            );

            // Jika dibatalkan dari booked/active dan dikenakan fee, posting beban pembatalan
            if (in_array($fromStatus, [HedgeStatus::BOOKED->value, HedgeStatus::ACTIVE->value], true) && $cancellationFeeIdr > 0 && $this->ledgerPoster !== null) {
                $idempotencyKey = "IDEM:TRS:FWD_CANCEL:{$contract->id}";
                $this->ledgerPoster->postForwardCancellationFee(
                    contractNumber: $contract->contract_number,
                    feeAmountIdr: $cancellationFeeIdr,
                    bankAccountCode: $bankAccountCode,
                    idempotencyKey: $idempotencyKey,
                    contractId: $contract->id,
                    userId: $actorId
                );
            }

            return $cancelled->fresh();
        });
    }
}
