<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Actions;

use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\StateMachines\EnterpriseBudgetCycleStateMachine;

/**
 * Aksi Penegakan Persetujuan Anggaran Berdasarkan Prinsip Four-Eyes (54.1 & State Machine).
 * Memvalidasi transisi siklus hidup anggaran korporat dan menegakkan larangan mutlak
 * bagi pembuat usulan anggaran untuk menyetujui anggarannya sendiri.
 */
class EnforceFourEyesBudgetApprovalAction
{
    public function __construct(
        protected readonly EnterpriseBudgetCycleStateMachine $stateMachine
    ) {}

    public function execute(
        EnterpriseBudget $budget,
        string $targetStatus,
        int $operatorId,
        ?string $justification = null
    ): EnterpriseBudget {
        return $this->stateMachine->transition(
            budget: $budget,
            toStatus: $targetStatus,
            operatorId: $operatorId,
            reason: $justification
        );
    }
}
