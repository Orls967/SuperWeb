<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Actions;

use InvalidArgumentException;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;

class EnforceDepartmentBudgetGuardrailAction
{
    /**
     * @param  string  $mode  'spend'|'encumber'
     * @return array{
     *     budget_code: string,
     *     cost_center: string,
     *     allocated_idr: int,
     *     previous_spent_idr: int,
     *     new_spent_idr: int,
     *     encumbered_idr: int,
     *     remaining_available_idr: int,
     *     mode: string
     * }
     */
    public function execute(
        EnterpriseBudget $budget,
        int $requestedAmountIdr,
        string $mode = 'spend'
    ): array {
        if ($requestedAmountIdr <= 0) {
            throw new InvalidArgumentException('Requested budget amount must be greater than zero.');
        }

        $allocated = $budget->allocated_amount_idr;
        $encumbered = $budget->encumbered_amount_idr;
        $spent = $budget->spent_amount_idr;
        $available = $allocated - ($encumbered + $spent);

        if ($requestedAmountIdr > $available && $budget->control_type === 'HARD_STOP') {
            throw new InvalidArgumentException("Budget guardrail hard-stop: {$budget->budget_code} has only Rp ".number_format($available, 0, ',', '.').' available, requested Rp '.number_format($requestedAmountIdr, 0, ',', '.').'.');
        }

        if ($mode === 'encumber') {
            $budget->increment('encumbered_amount_idr', $requestedAmountIdr);
        } else {
            $budget->increment('spent_amount_idr', $requestedAmountIdr);
        }

        $fresh = $budget->fresh();

        return [
            'budget_code' => $fresh->budget_code,
            'cost_center' => $fresh->cost_center_code,
            'allocated_idr' => $fresh->allocated_amount_idr,
            'previous_spent_idr' => $spent,
            'new_spent_idr' => $fresh->spent_amount_idr,
            'encumbered_idr' => $fresh->encumbered_amount_idr,
            'remaining_available_idr' => $fresh->allocated_amount_idr - ($fresh->encumbered_amount_idr + $fresh->spent_amount_idr),
            'mode' => $mode,
        ];
    }
}
