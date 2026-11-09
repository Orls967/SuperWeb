<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\StateMachines;

use DomainException;
use Modules\Platform\Domain\Enums\ChangeRiskTier;
use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\Models\ChangeTransition;

class ChangeRequestStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['assessed', 'closed'],
        'assessed' => ['cab_review', 'approved', 'draft'],
        'cab_review' => ['approved', 'draft'],
        'approved' => ['scheduled', 'draft'],
        'scheduled' => ['deploying', 'approved'],
        'deploying' => ['verified', 'rolled_back'],
        'verified' => ['closed'],
        'rolled_back' => ['draft', 'closed'],
        'closed' => [],
    ];

    public function __construct(
        private readonly ChangeRequest $changeRequest
    ) {}

    public function canTransitionTo(ChangeStatus $targetStatus): bool
    {
        $current = $this->changeRequest->status;
        $target = $targetStatus->value;

        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];
        if (! in_array($target, $allowed, true)) {
            return false;
        }

        // Guard: Jika status assessed hendak ke approved langsung, tapi risk_score > 65 atau risk_tier high/critical,
        // wajib melewati cab_review
        if ($current === ChangeStatus::ASSESSED->value && $target === ChangeStatus::APPROVED->value) {
            if ($this->changeRequest->risk_score > 6500 || in_array($this->changeRequest->risk_tier, [ChangeRiskTier::HIGH->value, ChangeRiskTier::CRITICAL->value], true)) {
                return false;
            }
        }

        return true;
    }

    public function transitionTo(ChangeStatus $targetStatus, int $operatorId, ?string $reason = null, array $metadata = []): void
    {
        if (! $this->canTransitionTo($targetStatus)) {
            throw new DomainException(
                "Transisi status ilegal dari '{$this->changeRequest->status}' ke '{$targetStatus->value}' untuk Change Request {$this->changeRequest->reference_number}."
            );
        }

        $fromStatus = $this->changeRequest->status;
        $toStatus = $targetStatus->value;

        // Mutate model state
        $this->changeRequest->status = $toStatus;

        if ($targetStatus === ChangeStatus::DEPLOYING) {
            $this->changeRequest->deployed_at = now();
        } elseif ($targetStatus === ChangeStatus::CLOSED) {
            $this->changeRequest->closed_at = now();
        }

        $this->changeRequest->save();

        // Record transition audit trail
        ChangeTransition::create([
            'change_request_id' => $this->changeRequest->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'operator_id' => $operatorId,
            'reason' => $reason,
            'transition_metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
