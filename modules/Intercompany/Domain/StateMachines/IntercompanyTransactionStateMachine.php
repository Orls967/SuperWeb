<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\StateMachines;

use Modules\Intercompany\Domain\Enums\IntercompanyTransactionStatus;
use Modules\Intercompany\Domain\Exceptions\IntercompanyInvalidTransitionException;
use Modules\Intercompany\Domain\Exceptions\IntercompanyMakerCheckerViolationException;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\IntercompanyTransactionHistory;

class IntercompanyTransactionStateMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        IntercompanyTransactionStatus::DRAFT->value => [
            IntercompanyTransactionStatus::SUBMITTED->value,
            IntercompanyTransactionStatus::REVERSED->value,
        ],
        'matched' => [
            IntercompanyTransactionStatus::SUBMITTED->value,
            IntercompanyTransactionStatus::REVERSED->value,
        ],
        IntercompanyTransactionStatus::SUBMITTED->value => [
            IntercompanyTransactionStatus::ARM_LENGTH_VALIDATED->value,
            IntercompanyTransactionStatus::REVERSED->value,
        ],
        IntercompanyTransactionStatus::ARM_LENGTH_VALIDATED->value => [
            IntercompanyTransactionStatus::POSTED_SELLER->value,
            IntercompanyTransactionStatus::REVERSED->value,
        ],
        IntercompanyTransactionStatus::POSTED_SELLER->value => [
            IntercompanyTransactionStatus::POSTED_BUYER->value,
            IntercompanyTransactionStatus::REVERSED->value,
        ],
        IntercompanyTransactionStatus::POSTED_BUYER->value => [
            IntercompanyTransactionStatus::RECONCILED->value,
            IntercompanyTransactionStatus::REVERSED->value,
        ],
        IntercompanyTransactionStatus::RECONCILED->value => [
            IntercompanyTransactionStatus::ELIMINATED->value,
            IntercompanyTransactionStatus::REVERSED->value,
        ],
        IntercompanyTransactionStatus::ELIMINATED->value => [],
        IntercompanyTransactionStatus::REVERSED->value => [],
    ];

    public function __construct(
        protected IntercompanyTransaction $transaction
    ) {}

    public function canTransitionTo(IntercompanyTransactionStatus $targetStatus): bool
    {
        $current = $this->transaction->status;
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        return in_array($targetStatus->value, $allowed, true);
    }

    public function transitionTo(
        IntercompanyTransactionStatus $targetStatus,
        int $operatorId,
        string $reason = ''
    ): IntercompanyTransaction {
        if (! $this->canTransitionTo($targetStatus)) {
            throw new IntercompanyInvalidTransitionException(
                $this->transaction->status,
                $targetStatus->value
            );
        }

        // Maker-Checker guard: Pembuat dilarang menyetujui transaksi sendiri
        if (
            $targetStatus === IntercompanyTransactionStatus::ARM_LENGTH_VALIDATED
            && $this->transaction->created_by !== null
            && $this->transaction->created_by === $operatorId
        ) {
            throw new IntercompanyMakerCheckerViolationException($operatorId);
        }

        $oldStatus = $this->transaction->status;
        $this->transaction->status = $targetStatus->value;
        if ($targetStatus === IntercompanyTransactionStatus::ARM_LENGTH_VALIDATED) {
            $this->transaction->approved_by = $operatorId;
        }
        $this->transaction->save();

        IntercompanyTransactionHistory::create([
            'transaction_id' => $this->transaction->id,
            'from_status' => $oldStatus,
            'to_status' => $targetStatus->value,
            'reason' => $reason,
            'operator_id' => $operatorId,
        ]);

        return $this->transaction;
    }
}
