<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Modules\Treasury\Domain\Models\CashPool;

class ExecuteNotionalCashPoolSweepAction
{
    /**
     * Execute physical or notional cash pool zero-balance / target-balance sweep.
     *
     * @return array{cash_pool: CashPool, swept_amount_idr: int, sweep_direction: string}
     */
    public function execute(
        CashPool $cashPool,
        int $currentSubAccountBalanceIdr
    ): array {
        $targetBalance = (int) $cashPool->target_balance_idr;
        $variance = $currentSubAccountBalanceIdr - $targetBalance;

        // If variance > 0: sweep excess cash up to header account
        // If variance < 0: top up deficit from header account
        $direction = match (true) {
            $variance > 0 => 'upstream_sweep_to_header',
            $variance < 0 => 'downstream_topup_from_header',
            default => 'balanced_no_sweep',
        };

        $sweptAmountIdr = abs($variance);

        $cashPool->update([
            'last_swept_amount_idr' => $sweptAmountIdr,
            'last_swept_at' => now(),
        ]);

        return [
            'cash_pool' => $cashPool->fresh(),
            'swept_amount_idr' => $sweptAmountIdr,
            'sweep_direction' => $direction,
        ];
    }
}
