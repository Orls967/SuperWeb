<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\StateMachines\HedgeContractStateMachine;

class RollForwardContractAction
{
    public function __construct(
        protected readonly ?DocumentNumberingInterface $documentNumbering = null,
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly HedgeContractStateMachine $stateMachine = new HedgeContractStateMachine,
    ) {}

    /**
     * Memperpanjang/menggulung kontrak lindung nilai (Roll Forward) ke jatuh tempo baru.
     *
     * @return array{old_contract: ForwardContract, new_contract: ForwardContract}
     */
    public function execute(
        ForwardContract $oldContract,
        string $newMaturityDate,
        int $newForwardRateScaled,
        int $spotRateAtRollScaled,
        int $actorId,
        string $bankAccountCode = 'trs:bank_operational:HOLDING:IDR'
    ): array {
        $status = $oldContract->status instanceof HedgeStatus ? $oldContract->status->value : (string) $oldContract->status;
        if ($status !== HedgeStatus::ACTIVE->value) {
            throw new InvalidArgumentException("Kontrak forward {$oldContract->contract_number} hanya dapat di-roll dari status ACTIVE (status sekarang: {$status}).");
        }

        return DB::transaction(function () use ($oldContract, $newMaturityDate, $newForwardRateScaled, $spotRateAtRollScaled, $actorId, $bankAccountCode) {
            $notionalMinor = (int) $oldContract->notional_foreign_amount;
            $oldForwardRate = (int) $oldContract->forward_rate_scaled;

            $oldValueIdr = (int) intdiv($notionalMinor * $oldForwardRate, 1000000);
            $spotValueIdr = (int) intdiv($notionalMinor * $spotRateAtRollScaled, 1000000);
            $netDiffIdr = $spotValueIdr - $oldValueIdr;

            // Generate nomor kontrak baru
            $newContractNumber = $this->documentNumbering !== null
                ? $this->documentNumbering->nextNumber('HOLDING', 'FWD', true, 'FWD/{YYYY}/{MM}/', 4)
                : 'FWD-ROLL-'.date('Ymd').'-'.str_pad((string) (ForwardContract::count() + 1), 4, '0', STR_PAD_LEFT);

            /** @var ForwardContract $newContract */
            $newContract = ForwardContract::create([
                'contract_number' => $newContractNumber,
                'currency' => $oldContract->currency,
                'creator_id' => $actorId,
                'notional_foreign_amount' => $notionalMinor,
                'forward_rate_scaled' => $newForwardRateScaled,
                'contract_rate_numerator' => $newForwardRateScaled,
                'contract_rate_denominator' => 10000,
                'maturity_date' => $newMaturityDate,
                'status' => HedgeStatus::ACTIVE->value,
                'rolled_from_contract_id' => $oldContract->id,
                'mtm_value_idr' => 0,
            ]);

            $oldContract->update([
                'rolled_to_contract_id' => $newContract->id,
                'settlement_gain_loss_idr' => $netDiffIdr,
            ]);

            // Transisi kontrak lama ke ROLLED
            $this->stateMachine->transition(
                $oldContract,
                HedgeStatus::ROLLED,
                $actorId,
                "Perpanjangan kontrak ke nomor {$newContractNumber} jatuh tempo {$newMaturityDate}"
            );

            // Jika ada realisasi selisih nilai saat roll, posting ke ledger
            if ($this->ledgerPoster !== null && $netDiffIdr !== 0) {
                $idempotencyKey = "IDEM:TRS:FWD_ROLL:{$oldContract->id}";
                $this->ledgerPoster->postForwardSettlement(
                    contractNumber: $oldContract->contract_number,
                    netSettlementIdr: $netDiffIdr,
                    bankAccountCode: $bankAccountCode,
                    idempotencyKey: $idempotencyKey,
                    contractId: $oldContract->id,
                    userId: $actorId
                );
            }

            return [
                'old_contract' => $oldContract->fresh(),
                'new_contract' => $newContract->fresh(),
            ];
        });
    }
}
