<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\StateMachines\HedgeContractStateMachine;

class SettleForwardFxHedgeContractAction
{
    public function __construct(
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly HedgeContractStateMachine $stateMachine = new HedgeContractStateMachine,
    ) {}

    /**
     * Settle forward FX hedge contract at maturity date against current spot rate.
     *
     * @return array{contract: ForwardContract, net_settlement_idr: int, status: string}
     */
    public function execute(
        ForwardContract $contract,
        int $spotRateAtMaturityScaled,
        string $bankAccountCode = 'trs:bank_operational:HOLDING:IDR',
        ?int $userId = null
    ): array {
        $status = $contract->status instanceof HedgeStatus ? $contract->status->value : (string) $contract->status;
        if (in_array($status, [HedgeStatus::SETTLED->value, HedgeStatus::CANCELLED->value, HedgeStatus::ROLLED->value], true)) {
            throw new InvalidArgumentException(
                sprintf('Kontrak forward %s sudah dalam status %s.', $contract->contract_number, $status)
            );
        }

        if ($spotRateAtMaturityScaled <= 0) {
            throw new InvalidArgumentException('Kurs spot jatuh tempo harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($contract, $spotRateAtMaturityScaled, $bankAccountCode, $userId) {
            $notionalMinor = (int) $contract->notional_foreign_amount;
            $forwardRate = (int) $contract->forward_rate_scaled;

            // Contract value at forward rate vs spot rate
            $forwardValueIdr = (int) intdiv($notionalMinor * $forwardRate, 1000000);
            $spotValueIdr = (int) intdiv($notionalMinor * $spotRateAtMaturityScaled, 1000000);

            // Net gain/loss for corporate hedger
            $netSettlementIdr = $spotValueIdr - $forwardValueIdr;

            $contract->update([
                'mtm_value_idr' => $netSettlementIdr,
                'settlement_amount_idr' => $spotValueIdr,
                'settlement_gain_loss_idr' => $netSettlementIdr,
                'spot_rate_numerator' => $spotRateAtMaturityScaled,
                'spot_rate_denominator' => 10000,
            ]);

            // Transisi status ke SETTLED via State Machine
            $settled = $this->stateMachine->transition(
                $contract,
                HedgeStatus::SETTLED,
                $userId,
                "Penyelesaian jatuh tempo kurs spot {$spotRateAtMaturityScaled}"
            );

            // Posting ledger penyelesaian
            if ($this->ledgerPoster !== null) {
                $idempotencyKey = "IDEM:TRS:FWD_SETTLE:{$contract->id}";
                $this->ledgerPoster->postForwardSettlement(
                    contractNumber: $contract->contract_number,
                    netSettlementIdr: $netSettlementIdr,
                    bankAccountCode: $bankAccountCode,
                    idempotencyKey: $idempotencyKey,
                    contractId: $contract->id,
                    userId: $userId
                );
            }

            return [
                'contract' => $settled->fresh(),
                'net_settlement_idr' => $netSettlementIdr,
                'status' => 'settled',
            ];
        });
    }
}
