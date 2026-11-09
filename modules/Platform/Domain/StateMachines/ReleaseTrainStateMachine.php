<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\StateMachines;

use DomainException;
use Modules\Platform\Domain\Enums\ReleaseTrainStatus;
use Modules\Platform\Domain\Models\ReleaseTrain;

class ReleaseTrainStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'open' => ['frozen', 'cancelled'],
        'frozen' => ['staging', 'open', 'cancelled'],
        'staging' => ['deploying', 'frozen', 'cancelled'],
        'deploying' => ['active', 'staging', 'cancelled'],
        'active' => ['archived'],
        'cancelled' => ['open'],
        'archived' => [],
    ];

    public function __construct(
        private readonly ReleaseTrain $releaseTrain
    ) {}

    public function canTransitionTo(ReleaseTrainStatus $targetStatus): bool
    {
        $current = $this->releaseTrain->status;
        $target = $targetStatus->value;

        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        return in_array($target, $allowed, true);
    }

    public function transitionTo(ReleaseTrainStatus $targetStatus, ?string $reason = null): void
    {
        if (! $this->canTransitionTo($targetStatus)) {
            throw new DomainException(
                "Transisi status release train ilegal dari '{$this->releaseTrain->status}' ke '{$targetStatus->value}'."
            );
        }

        $this->releaseTrain->status = $targetStatus->value;
        if ($targetStatus === ReleaseTrainStatus::ACTIVE) {
            $this->releaseTrain->deployed_at = now();
        }

        $this->releaseTrain->save();
    }
}
