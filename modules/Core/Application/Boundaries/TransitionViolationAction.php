<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Modules\Core\Domain\StateMachines\BoundaryViolationStateMachine;

final class TransitionViolationAction
{
    public function __construct(
        private readonly BoundaryViolationStateMachine $stateMachine = new BoundaryViolationStateMachine,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        CoreBoundaryViolation $violation,
        ViolationStatus $targetStatus,
        string $actorId,
        string $reason,
        array $metadata = [],
    ): CoreBoundaryViolation {
        return $this->stateMachine->transition(
            $violation,
            $targetStatus,
            $actorId,
            $reason,
            $metadata,
        );
    }
}

