<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\StateMachines;

use Modules\EnterpriseFinance\Domain\Exceptions\BudgetInvalidTransitionException;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetMakerCheckerViolationException;
use Modules\EnterpriseFinance\Domain\Models\BudgetHistory;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;

class EnterpriseBudgetCycleStateMachine
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_REVIEWED = 'REVIEWED';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_REVISED = 'REVISED';

    public const STATUS_FROZEN = 'FROZEN';

    public const STATUS_CLOSED = 'CLOSED';

    /**
     * Matriks transisi siklus hidup anggaran korporat (8 status).
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED, self::STATUS_CLOSED],
        self::STATUS_SUBMITTED => [self::STATUS_REVIEWED, self::STATUS_DRAFT, self::STATUS_REVISED],
        self::STATUS_REVIEWED => [self::STATUS_APPROVED, self::STATUS_REVISED, self::STATUS_DRAFT],
        self::STATUS_APPROVED => [self::STATUS_ACTIVE, self::STATUS_REVISED, self::STATUS_FROZEN],
        self::STATUS_ACTIVE => [self::STATUS_REVISED, self::STATUS_FROZEN, self::STATUS_CLOSED],
        self::STATUS_REVISED => [self::STATUS_SUBMITTED, self::STATUS_DRAFT, self::STATUS_CLOSED],
        self::STATUS_FROZEN => [self::STATUS_ACTIVE, self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
    ];

    /**
     * Eksekusi transisi status anggaran dengan penegakan Four-Eyes Principle.
     *
     * @throws BudgetInvalidTransitionException
     * @throws BudgetMakerCheckerViolationException
     */
    public function transition(
        EnterpriseBudget $budget,
        string $toStatus,
        int $operatorId,
        ?string $reason = null
    ): EnterpriseBudget {
        $fromStatus = $budget->status ?? self::STATUS_DRAFT;
        $allowed = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];

        if (! in_array($toStatus, $allowed, true)) {
            throw new BudgetInvalidTransitionException($fromStatus, $toStatus);
        }

        // Penegakan Four-Eyes Principle (Maker-Checker Guardrail)
        if ($toStatus === self::STATUS_APPROVED) {
            if ($budget->created_by !== null && (int) $budget->created_by === $operatorId) {
                throw new BudgetMakerCheckerViolationException(
                    "Pelanggaran Four-Eyes Principle: Operator pembuat (#{$operatorId}) dilarang menyetujui anggaran miliknya sendiri."
                );
            }
            $budget->approved_by = $operatorId;
        }

        $budget->status = $toStatus;
        $budget->save();

        if ($budget->id !== null) {
            BudgetHistory::create([
                'budget_id' => $budget->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'operator_id' => $operatorId,
            ]);
        }

        return $budget;
    }

    /**
     * Mendapatkan daftar status transisi yang diizinkan untuk status saat ini.
     *
     * @return string[]
     */
    public function getAllowedTransitions(string $currentStatus): array
    {
        return self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
    }
}
