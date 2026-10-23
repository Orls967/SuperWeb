<?php

declare(strict_types=1);

namespace Modules\Agency\Application\Actions;

use InvalidArgumentException;
use Modules\Agency\Domain\Models\CommissionAccrual;

class ReleaseClawbackHoldCommissionAction
{
    /**
     * Release hold on commission accrual to payable, or execute clawback deduction.
     *
     * @return array{accrual: CommissionAccrual, status: string, action_taken: string}
     */
    public function execute(
        CommissionAccrual $accrual,
        bool $isClawback = false,
        ?string $reason = null
    ): array {
        if ($accrual->status !== 'hold') {
            throw new InvalidArgumentException(
                sprintf('Akrual komisi %d tidak berada dalam status hold (status saat ini: %s).', (int) $accrual->id, $accrual->status)
            );
        }

        if ($isClawback) {
            $accrual->update([
                'status' => 'clawback',
                'note' => ($accrual->note ? $accrual->note.' | ' : '').'Clawback: '.($reason ?? 'Order dibatalkan/diretur pelanggan.'),
            ]);

            return [
                'accrual' => $accrual->fresh(),
                'status' => 'clawback',
                'action_taken' => 'clawback_executed',
            ];
        }

        $accrual->update([
            'status' => 'payable',
            'note' => ($accrual->note ? $accrual->note.' | ' : '').'Hold dilepas menjadi siap dibayarkan (payable).',
        ]);

        return [
            'accrual' => $accrual->fresh(),
            'status' => 'payable',
            'action_taken' => 'released_to_payable',
        ];
    }
}
