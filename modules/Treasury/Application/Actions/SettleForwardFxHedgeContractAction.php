<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use InvalidArgumentException;
use Modules\Treasury\Domain\Models\ForwardContract;

class SettleForwardFxHedgeContractAction
{
    /**
     * Settle forward FX hedge contract at maturity date against current spot rate.
     *
     * @return array{contract: ForwardContract, net_settlement_idr: int, status: string}
     */
    public function execute(
        ForwardContract $contract,
        int $spotRateAtMaturityScaled
    ): array {
        if ($contract->status === 'settled' || $contract->status === 'cancelled') {
            throw new InvalidArgumentException(
                sprintf('Kontrak forward %s sudah dalam status %s.', $contract->contract_number, $contract->status)
            );
        }

        if ($spotRateAtMaturityScaled <= 0) {
            throw new InvalidArgumentException('Kurs spot jatuh tempo harus lebih besar dari 0.');
        }

        $notionalMinor = (int) $contract->notional_foreign_amount;
        $forwardRate = (int) $contract->forward_rate_scaled;

        // Contract value at forward rate vs spot rate
        $forwardValueIdr = (int) intdiv($notionalMinor * $forwardRate, 1000000);
        $spotValueIdr = (int) intdiv($notionalMinor * $spotRateAtMaturityScaled, 1000000);

        // Net gain/loss for corporate hedger
        $netSettlementIdr = $spotValueIdr - $forwardValueIdr;

        $contract->update([
            'mtm_value_idr' => $netSettlementIdr,
            'status' => 'settled',
        ]);

        return [
            'contract' => $contract->fresh(),
            'net_settlement_idr' => $netSettlementIdr,
            'status' => 'settled',
        ];
    }
}
